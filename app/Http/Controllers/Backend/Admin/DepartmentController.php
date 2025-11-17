<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Models\User;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Specialty;
use App\Models\Department;
use Illuminate\Http\Request;
use App\Models\ClinicDepartment;
use App\Models\EmployeeJobTitle;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;

class DepartmentController extends Controller{


    public function addDepartment(){
        return view('Backend.admin.departments.add');
    }


    public function storeDepartment(Request $request){
        $normalizedName = strtolower(trim($request->name));
        if (Department::whereRaw('LOWER(name) = ?', [$normalizedName])->exists()) {
            return response()->json(['data' => 0]);
        } else {
            $department = Department::create([
                'name' => $request->name,
                'description' => $request->description,
                'status' => $request->status,
            ]);
            return response()->json(['data' => 1]);
        }
    }





    public function viewDepartments(){
        $departments = Department::orderBy('id', 'asc')->paginate(10);
        return view('Backend.admin.departments.view', compact('departments'));
    }





    public function detailsDepartment($id){
        $department = Department::with('clinics', 'doctors')->findOrFail($id);
        $count_clinics = $department->clinics()->count();
        $count_doctor = $department->doctors()->count();

        return view('Backend.admin.departments.details', compact(
            'department',
            'count_clinics',
            'count_doctor'
        ));
    }





    public function editDepartment($id){
        $department = Department::findOrFail($id);
        return view('Backend.admin.departments.edit', compact('department'));
    }


    public function updateDepartment(Request $request, $id){
        $department = Department::findOrFail($id);
        $normalizedName = strtolower(trim($request->name));
        $exists = Department::whereRaw('LOWER(name) = ?', [$normalizedName])->where('id', '!=', $id)->exists();
        if ($exists) {
            return response()->json(['data' => 0]);
        }
        $department->update([
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status,
        ]);
        return response()->json(['data' => 1]);
    }





    public function deleteDepartment($id){
        $department = Department::findOrFail($id);
        $employeeIds = Employee::where('department_id', $department->id)->pluck('id');
        $userIds = Employee::whereIn('id', $employeeIds)->pluck('user_id');
        $doctorIds = Doctor::whereIn('employee_id', $employeeIds)->pluck('id');

        Doctor::whereIn('id', $doctorIds)->delete();
        Employee::whereIn('id', $employeeIds)->delete();
        User::whereIn('id', $userIds)->delete();

        $department->clinics()->detach();
        $department->delete();
        return response()->json(['success' => true]);
    }








    public function viewDepartmentsManagers(){
        $departments_managers = User::role('department_manager')->paginate(12);
        return view('Backend.admin.departments.departments_managers.view' , compact('departments_managers'));
    }


    public function searchDepartmentsManagers(Request $request){
        $keyword = trim((string) $request->input('keyword', ''));
        $filter  = $request->input('filter', '');

        $departments_managers = User::role('department_manager')->with([
            'employee:id,user_id,clinic_id,department_id,status',
            'employee.clinic:id,name',
        ]);

        if ($keyword !== '') {
            switch ($filter) {
                case 'name':
                    $departments_managers->where('name', 'LIKE', "{$keyword}%");
                    break;

                case 'clinic':
                    // ✅ إصلاح رئيسي: لازم تمر من العلاقة employee أولًا
                    $departments_managers->whereHas('employee', function ($q) use ($keyword) {
                        $q->whereHas('clinic', function ($qq) use ($keyword) {
                            $qq->where('name', 'LIKE', "{$keyword}%");
                        });
                    });
                    break;

                default:
                    $departments_managers->where(function ($q) use ($keyword) {
                        $q->where('name', 'LIKE', "%{$keyword}%")
                            ->orWhereHas('employee', function ($qq) use ($keyword) {
                                $qq->whereHas('clinic', function ($qqq) use ($keyword) {
                                    $qqq->where('name', 'LIKE', "%{$keyword}%");
                                });
                            });
                    });
                    break;
            }
        }

        $departments_managers = $departments_managers->orderBy('id', 'asc')->paginate(12);

        $view = view('Backend.admin.departments.departments_managers.search', compact('departments_managers'))->render();
        $pagination = $departments_managers->total() > 12 ? $departments_managers->links('pagination::bootstrap-4')->render() : '';

        return response()->json([
            'html'       => $view,
            'pagination' => $pagination,
            'count'      => $departments_managers->total(),
            'searching'  => $keyword !== '',
        ]);
    }









    public function profileDepartmentManager($id){
        $department_manager = User::findOrFail($id);
        return view('Backend.admin.departments.departments_managers.profile', compact('department_manager'));
    }




    public function editDepartmentManager($id){
        $department_manager = User::findOrFail($id);
        $clinics = Clinic::all();
        $working_days = $department_manager->employee->working_days ?? [];
        return view('Backend.admin.departments.departments_managers.edit', compact( 'department_manager', 'clinics', 'working_days'));
    }


    public function updateDepartmentManager(Request $request, $id){
        $department_manager = User::findOrFail($id);
        $employee = Employee::where('user_id', $department_manager->id)->first();

        if (User::where('name', $request->name)->where('id', '!=', $id)->exists() || User::where('email', $request->email)->where('id', '!=', $id)->exists()) {
            return response()->json(['data' => 0]);
        }else{
            $imagePath = $department_manager->image;
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $imageName = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('assets/img/department_manager'), $imageName);
                $imagePath = 'assets/img/department_manager/' . $imageName;
            }


            $password = $department_manager->password;
            if ($request->filled('password')) {
                $password = Hash::make($request->password);
            }

            $department_manager->update([
                'name' => $request->name ,
                'email' => $request->email ,
                'phone' => $request->phone,
                'password' => $password,
                'image' => $imagePath,
                'address' => $request->address,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
            ]);

            $employee->update([
                'user_id' => $department_manager->id ,
                'clinic_id' => $request->clinic_id ,
                'department_id' => $request->department_id ,
                'status' => $request->status,
                'work_start_time' => $request->work_start_time,
                'work_end_time' => $request->work_end_time,
                'working_days' => $request->working_days,
                'short_biography' => $request->short_biography,
            ]);

            return response()->json(['data' => 1]);
        }
    }




    public function deleteDepartmentManager($id){
        $user = User::findOrFail($id);
        $employee = Employee::where('user_id', $id)->firstOrFail();

        if ($user->hasRole('doctor')) {
            $employee->update(['job_title' => 'Doctor']);
            $user->removeRole('department_manager');
            return response()->json(['success' => true]);
        } else {
            $user->removeRole('department_manager');
            $employee->delete();
            $user->delete();

            return response()->json(['success' => true]);
        }
    }
    public function addDepartmentManager()
{
    $departments = Department::all();
    $employees = Employee::with('user')->get();
    
    return view('Backend.admin.departments.managers.add', compact('departments', 'employees'));
}

public function storeDepartmentManager(Request $request)
{
    $existingManager = DepartmentManager::where('department_id', $request->department_id)
        ->where('is_active', true)
        ->exists();

    if ($existingManager) {
        return response()->json(['data' => 0]);
    }

    $employee = Employee::where('user_id', $request->user_id)->first();
    if (!$employee) {
        return response()->json(['data' => 0]);
    }

    DepartmentManager::create([
        'user_id' => $request->user_id,
        'department_id' => $request->department_id,
        'start_date' => $request->start_date,
        'is_active' => true,
    ]);

    return response()->json(['data' => 1]);
}

public function viewDepartmentManagers()
{
    $managers = DepartmentManager::with(['user', 'department'])
        ->orderBy('id', 'asc')
        ->paginate(10);
        
    return view('Backend.admin.departments.managers.view', compact('managers'));
}

public function editDepartmentManager($id)
{
    $manager = DepartmentManager::with(['user', 'department'])->findOrFail($id);
    $departments = Department::all();
    $employees = Employee::with('user')->get();
    
    return view('Backend.admin.departments.managers.edit', compact('manager', 'departments', 'employees'));
}

public function updateDepartmentManager(Request $request, $id)
{
    $manager = DepartmentManager::findOrFail($id);

    if ($manager->department_id != $request->department_id) {
        $existingManager = DepartmentManager::where('department_id', $request->department_id)
            ->where('is_active', true)
            ->where('id', '!=', $id)
            ->exists();

        if ($existingManager) {
            return response()->json(['data' => 0]);
        }
    }

    $manager->update([
        'user_id' => $request->user_id,
        'department_id' => $request->department_id,
        'start_date' => $request->start_date,
        'end_date' => $request->end_date,
        'is_active' => $request->is_active,
    ]);

    return response()->json(['data' => 1]);
}

public function deleteDepartmentManager($id)
{
    $manager = DepartmentManager::findOrFail($id);
    $manager->delete();

    return response()->json(['success' => true]);
}

public function deactivateDepartmentManager($id)
{
    $manager = DepartmentManager::findOrFail($id);
    $manager->update([
        'is_active' => false,
        'end_date' => now(),
    ]);

    return response()->json(['data' => 1]);
}
}

