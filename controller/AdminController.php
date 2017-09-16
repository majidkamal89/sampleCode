<?php

namespace App\Http\Controllers;

use App\Carrier;
use App\ChangeLogs;
use App\ContactUs;
use App\Module;
use App\ModuleResponses;
use App\Notification;
use App\Response;
use App\Role;
use App\ThirdParty;
use App\ThirdPartyUser;
use App\User;
use App\UserModuleRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->users = new User();
        $this->modules = new Module();
        $this->roles = new Role();
        $this->userModuleRoles = new UserModuleRole();
        $this->responses = new Response();
    }

    public function index()
    {
        if (Auth::user() && Auth::user()->is_admin == 1) {
            return redirect('/admin/dashboard');
        }
        return view('admin.login');
    }

    public function dashboard(Request $request)
    {
        $assignedUsers = $this->users->getAssignedUsersWithDetails();
        $newUsers = $this->users->getNewUsers();
        $modules = $this->modules->getAllModules();
        $roles = $this->roles->getAllRoles();
        if (Auth::user()->is_admin != 1) {
            //$modules = Auth::user()->getUserModulesWithRoleAdmin(1);
            if (count($modules) > 0) {
                $modules = \DB::table('modules')->select(['modules.*'])
                    ->join('user_module_roles', 'user_module_roles.module_id', '=', 'modules.id')
                    ->where('user_module_roles.role_id', '=', 1)
                    ->where('user_module_roles.user_id', '=', Auth::user()->id)
                    ->get();
            }
        }
        $users = collect([
            'assignedUsers' => $assignedUsers,
            'newUsers' => $newUsers,
            //'modules' => $modules,
            //'roles' => $roles
        ]);
        if ($request->ajax()) {
            return response()->json(view('admin.dashboard_assigned_user', compact('users'))->render());
        }
        return view('admin.dashboard')->with('users', $users);
    }

    public function assignRole($id = false)
    {
//        $users = $this->users->getNewUsers();
        $users = $this->users->getAllUsersWithDetails();
        $modules = $this->modules->getAllModules();
        $roles = $this->roles->getAllRoles();
        if (Auth::user()->is_admin != 1) {
            //$modules = Auth::user()->getUserModulesWithRoleAdmin(1);
            if (count($modules) > 0) {
                $modules = \DB::table('modules')->select(['modules.*'])
                    ->join('user_module_roles', 'user_module_roles.module_id', '=', 'modules.id')
                    ->where('user_module_roles.role_id', '=', 1)
                    ->where('user_module_roles.user_id', '=', Auth::user()->id)
                    ->get();
            }
        }
        if ($id) {
            $data = [
                'users' => $users,
                'modules' => $modules,
                'roles' => $roles,
                'id' => $id
            ];
        } else {
            $data = [
                'users' => $users,
                'modules' => $modules,
                'roles' => $roles,
                'id' => Null
            ];
        }
        return view('admin.assignRole')->with('data', $data);
    }

    public function assignModuleAndRole(Request $request)
    {
        $if_duplicate = UserModuleRole::where('user_id', $request->user)
            ->where('module_id', $request->module)
            ->where('role_id', $request->role)
            ->first();
        if (count($if_duplicate)) {
            return collect([
                'status' => 'failure',
                'message' => 'Role and Module already exist against this user.'
            ]);
        }
        $result = $this->userModuleRoles->assignRole($request);
        return $result;
    }

    public function updateModuleAndRole(Request $request)
    {
        $update = $this->userModuleRoles->updateRole($request);
        return $update;
    }

    public function userProfile($id = false)
    {
        if ($id) {
            $userDetails = $this->users->getUserWithAllDetails($id);
            $modules = $this->modules->getAllModules();
            if (Auth::user()->is_admin != 1) {
                //$modules = Auth::user()->getUserModulesWithRoleAdmin(1);
                if (count($modules) > 0) {
                    $modules = \DB::table('modules')->select(['modules.*'])
                        ->join('user_module_roles', 'user_module_roles.module_id', '=', 'modules.id')
                        ->where('user_module_roles.role_id', '=', 1)
                        ->where('user_module_roles.user_id', '=', Auth::user()->id)
                        ->get();
                }
            }
            $thirdPartyData = ThirdParty::where('status', 0)->get();
            $thirdPartyData2 = ThirdPartyUser::where('user_id', $id)->get();
            foreach ($thirdPartyData as $key => $val) {
                if (count($thirdPartyData2->where('thirdparty_id', $val->id)) > 0) {
                    $thirdPartyData[$key]->user_id = $id;
                } else {
                    $thirdPartyData[$key]->user_id = 0;
                }
            }
            $data = [
                'userDetails' => $userDetails,
                'thirdpartylinks' => $thirdPartyData,
                //'modules' => $modules
            ];
            if (Auth::user()->is_admin != 1) {
                $userData = DB::table('users')->select(['users.first_name', 'user_module_roles.id', 'modules.name', 'modules.id as moduleID', 'roles.role_name', 'roles.id as roleID'])
                    ->join('user_module_roles', 'user_module_roles.user_id', '=', 'users.id')
                    ->join('modules', 'modules.id', '=', 'user_module_roles.module_id')
                    ->join('roles', 'roles.id', '=', 'user_module_roles.role_id')
                    ->where('users.id', $id)->get();

                $adminUserData = DB::table('users')->select(['users.first_name', 'user_module_roles.id', 'modules.name', 'modules.id as moduleID'])
                    ->join('user_module_roles', 'user_module_roles.user_id', '=', 'users.id')
                    ->join('modules', 'modules.id', '=', 'user_module_roles.module_id')
                    ->where('users.id', Auth::user()->id)->where('user_module_roles.role_id', 1)->get();

                foreach ($adminUserData as $key => $val) {
                    foreach ($userData as $key1 => $val1) {
                        if ($val->moduleID == $val1->moduleID) {
                            $adminUserData[$key]->user_role_id = $val1->roleID;
                        } else {
                            $adminUserData[$key]->user_role_id = 0;
                        }
                    }
                }
                $data['admin_modules'] = $adminUserData;
            } else {
                $userData = DB::table('users')->select(['users.first_name', 'users.id', 'user_module_roles.id as module_role_id', 'modules.name', 'modules.id as moduleID', 'roles.role_name', 'roles.id as roleID'])
                    ->join('user_module_roles', 'user_module_roles.user_id', '=', 'users.id')
                    ->join('modules', 'modules.id', '=', 'user_module_roles.module_id')
                    ->join('roles', 'roles.id', '=', 'user_module_roles.role_id')
                    ->where('users.id', '!=', 0)->get();
                $userData = $userData->where('id', $id);
                $allModules = $modules;
                foreach ($allModules as $k1 => $v1) {
                    $module_id = $userData->where('moduleID', $v1->id)->first();
                    if (!empty($module_id)) {
                        $allModules[$k1]->user_role_id = $module_id->roleID;
                    } else {
                        $allModules[$k1]->user_role_id = 0;
                    }
                    $allModules[$k1]->moduleID = $v1->id;
                }
                $data['admin_modules'] = $allModules;
            }
            return view('admin.userProfile')->with('data', $data);
        } else {
            return Redirect::to('/404');
        }
    }

    public function module($id = false)
    {
        if ($id) {
            $response = $this->responses->getAllResponsesWithPagination($id);
            foreach ($response as $key => $item) {
                $response[$key]->response_detail = $item->response;
            }
            $modules = $this->modules->getAllModules();
            $assignedUsers = \DB::table('user_module_roles')->select(['users.*', 'roles.role_name'])
                ->join('users', 'user_module_roles.user_id', '=', 'users.id')
                ->join('roles', 'user_module_roles.role_id', '=', 'roles.id')
                ->where('user_module_roles.module_id', $id)
                ->get();

            if (Auth::user()->is_admin != 1) {
                if (count($modules) > 0) {
                    $modules = \DB::table('modules')->select(['modules.*'])
                        ->join('user_module_roles', 'user_module_roles.module_id', '=', 'modules.id')
                        ->where('user_module_roles.role_id', '=', 1)
                        ->where('user_module_roles.user_id', '=', Auth::user()->id)
                        ->where('modules.id', '=', $id)
                        ->get();
                }
                $assignedUsers = Auth::user()->getModuleUsers($modules[0]->id);
            }
            $data = [
                'responses' => $response,
                'assignedUsers' => $assignedUsers
            ];
            if (\Request::ajax()) {
                return Response()->json(view('admin.responses_table', compact('data'))->render());
            }
            return view('admin.moduleDetail')->with('data', $data)->with('module_id', $id);
        } else {
            return redirect('/404');
        }
    }

    public function response($responseId = false)
    {
        if ($responseId) {
            $response = $this->responses->getResponseWithDetails($responseId);
            if (empty($response)) {
                return Redirect::back();
            }
            if ($response->user_id > 0) {
                $userName = User::where('id', $response->user_id)->first(['first_name', 'last_name']);
            } else {
                $this->users->first_name = 'Visitor';
                $this->users->last_name = '';
                $userName = $this->users;
            }
            $data = [
                'response' => $response
            ];
            session()->forget(['success', 'warning', 'error']);
            if ($response->status == 1) {
                session()->flash('success', 'This inquiry has been submitted and needs processing.');
            }
            if ($response->status == 2) {
                session()->flash('warning', 'This inquiry is in processing.');
            }
            if ($response->status == 3) {
                session()->flash('error', 'This inquiry has been resolved.');
            }
            if ($response->status == 4) {
                session()->flash('success', 'This inquiry has been approved.');
            }
            return view('admin.responseDetail')->with('data', $data)->with('submitBy', '' . $userName->first_name . ' ' . $userName->last_name . '');
        } else {
            return \redirect('/404');
        }
    }

    public function userModules()
    {
        $userModules = $this->userModuleRoles->getAllUserModules();
    }

    public function updateStatus(Request $request)
    {
        if (!empty($request->message)) {
            $userEmail = Response::where('id', $request->id)->first();
            $sendEmail = $this->responses->sendEmail(['address' => ['email' => json_decode($userEmail->response)->email]], $request->subject, $request->message);
            $result = Response::find($request->id)->update(['status' => $request->status]);
        } else {
            $result = Response::find($request->id)->update(['status' => $request->status]);
        }
        if ($result) {
            return Response()->json(['success' => true]);
        }
        return Response()->json(['success' => false]);
    }

    //Method to list contact us data on admin side
    public function userContacts()
    {
        $modules = $this->modules->getAllModules();

        $users = collect([
            'modules' => $modules
        ]);
        $contacts = ContactUs::paginate(10);
        return view('admin.contact_us', compact('contacts'))->with('users', $users);
    }

    // Method to update the contact us status
    public function updateUserContact($id, $status)
    {
        $status_val = ($status == 1) ? 0 : 1;
        $update = ContactUs::find($id)->update(['status' => $status_val]);
        if ($update) {
            $contacts = ContactUs::paginate(10);
            return response()->json(view('admin.contact_us_table', compact('contacts'))->render());
        }
        return 1;
    }


    //Method to lget a form to load all users from a csv file.
    public function importform()
    {
        $dataArray['new'] = [];
        $dataArray['failed'] = [];
        $dataArray['inserted'] = [];
        return view('admin.import-user-form', compact('dataArray'));
    }


    /*
     *  Method to import old users from a csv file.
     */
    public function importAll(Request $request)
    {

        $rules = [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|max:255|unique:users'
        ];

        if (Input::file('datafile')) {
            /*-- upload file --*/
            try {
                $sheetdata = ExcelController::upload_excel(Input::file('datafile'))->toArray();
                for ($i = 0; $i < count($sheetdata); $i++) {
                    $array = [

                        'first_name' => isset($sheetdata[$i]['first_name']) ? $sheetdata[$i]['first_name'] : '',
                        'last_name' => isset($sheetdata[$i]['last_name']) ? $sheetdata[$i]['last_name'] : '',
                        'phone' => isset($sheetdata[$i]['phone']) ? $sheetdata[$i]['phone'] : '',
                        'npn' => isset($sheetdata[$i]['npn']) ? $sheetdata[$i]['npn'] : '',
                        'email' => isset($sheetdata[$i]['email']) ? $sheetdata[$i]['email'] : '',
                        'state' => isset($sheetdata[$i]['state']) ? $sheetdata[$i]['state'] : '',
                        'password' => isset($sheetdata[$i]['password']) ? $sheetdata[$i]['password'] : '',

                        'carriers' => isset($sheetdata[$i]['carriers']) ? $sheetdata[$i]['carriers'] : '',
                        'gender' => isset($sheetdata[$i]['gender']) ? $sheetdata[$i]['gender'] : '',
                        'source' => isset($sheetdata[$i]['source']) ? $sheetdata[$i]['source'] : '',
                        'address' => isset($sheetdata[$i]['address']) ? $sheetdata[$i]['address'] : '',
                        'city' => isset($sheetdata[$i]['city']) ? $sheetdata[$i]['city'] : '',

                        'is_admin' => 0,
                    ];
                    $validator = Validator::make($array, $rules);
                    if ($validator->fails()) {
                        session()->flash('error', $validator->errors()->first());
                        return redirect()->route('dashboard');
                    } else {
                        User::create($array);
                    }
                }
            } catch (\Exception $e) {
                session()->flash('error', 'Something went wrong, please try again later.');
            }
        }
        return redirect()->route('dashboard');
    }

    /*
     * A method to download a sample file
     */
    public function downloadSample()
    {
        return response()->download(storage_path('exports/ImportUsers.xlsx'));
    }


    public function changeLog()
    {

        $modules = $this->modules->getAllModules();

        $changeLogs = ChangeLogs::orderby('id', 'desc')->get();

        $types = [1 => 'calendar_events', 2 => 'carriers', 3 => 'contact_us', 4 => 'module_responses', 5 => 'modules', 6 => 'notifications', 7 => 'responses', 8 => 'roles', 9 => 'team_members', 10 => 'user_carriers', 11 => 'user_module_roles', 12 => 'users'];

        $modalData = [];

        foreach ($changeLogs as $key => $value) {

            if ($value->task_type == 1) {
                $status = 'New record added';
            } else if ($value->task_type == 2) {
                $status = 'Record updated';
            } else if ($value->task_type == 3) {
                $status = 'Record deleted';
            }
            $response = \DB::table($value->table_name)->where('id', $value->record_id)->first();

            if (empty($response)) {
                $response = new ChangeLogs();
                $response->type = 1;
                $response->module = ucwords(str_replace('_', ' ', $value->table_name) . ' table');
                if ($value->task_type == 3) {
                    $response->first_name = '';
                    if (!empty(json_decode($value->prevValue)->name)) {
                        $response->first_name = json_decode($value->prevValue)->name;
                    }
                    if (!empty(json_decode($value->prevValue)->first_name)) {
                        $response->first_name = json_decode($value->prevValue)->first_name;
                    }
                    $response->last_name = !empty(json_decode($value->prevValue)->last_name) ? json_decode($value->prevValue)->last_name : '';
                } else {
                    $response->first_name = '';
                    if (!empty(json_decode($value->currentValue)->name)) {
                        $response->first_name = json_decode($value->currentValue)->name;
                    }
                    if (!empty(json_decode($value->currentValue)->first_name)) {
                        $response->first_name = json_decode($value->currentValue)->first_name;
                    }
                    $response->last_name = !empty(json_decode($value->currentValue)->last_name) ? json_decode($value->currentValue)->last_name : '';
                }
                $response->logId = $value->id;
                $response->statusType = 3;
                $response->status = 'Record deleted';
                $response->table = $value->table_name;
                $modalData[$value->table_name][] = $response;
            } else {

                switch ($value->table_name) {

                    case ($value->table_name == 'user_module_roles' || $value->table_name == 'notifications');

                        $module = $modules->where('id', $response->module_id)->first();
                        $user = User::where('id', $response->user_id)->first();
                        $response->type = 1;
                        $response->module = (!empty($module->name) ? $module->name : 'Module Deleted');
                        $response->first_name = $user->first_name;
                        $response->last_name = $user->last_name;

                        break;

                    case 'calendar_events';

                        $response->type = 2;
                        $response->module = $response->name;
                        $response->first_name = 'Super';
                        $response->last_name = 'Admin';


                        break;

                    case 'carriers';

                        $response->type = 3;
                        $response->module = $response->name;
                        $response->first_name = 'Super';
                        $response->last_name = 'Admin';


                        break;

                    case 'contact_us';

                        $name = explode(' ', $response->name);

                        $response->type = 4;
                        $response->module = 'Contact us subject' . $response->subject;
                        $response->first_name = (!empty($name[0]) ? $name[0] : '');
                        $response->last_name = (!empty($name[1]) ? $name[1] : '');


                        break;

                    case 'module_responses';

                        $moduleName = Module::find($response->module_id);
                        $response = Response::find($response->response_id);

                        $response->type = 5;
                        $response->module = (!empty($moduleName->name) ? $moduleName->name : 'Module Deleted');
                        $response->first_name = (!empty(json_decode($response->response)->first_name) ? json_decode($response->response)->first_name : '');
                        $response->last_name = (!empty(json_decode($response->response)->last_name) ? json_decode($response->response)->last_name : '');


                        break;

                    case 'modules';

                        $response->type = 6;
                        $response->module = $response->name;
                        $response->first_name = 'Super';
                        $response->last_name = 'Admin';

                        break;

                    case 'responses';

                        $moduleName = Module::find(json_decode($response->response)->module_id);
                        $response->type = 7;
                        if (!empty(json_decode($response->response)->module_name) && json_decode($response->response)->module_name == 'Contact Us') {
                            $response->module = (!empty($moduleName->name) ? $moduleName->name : 'Module Deleted');
                            $response->first_name = ucwords(json_decode($response->response)->name);
                            $response->last_name = '';
                        } else {
                            $response->module = (!empty($moduleName->name) ? $moduleName->name : 'Module Deleted');
                            $response->first_name = ucwords(json_decode($response->response)->first_name);
                            $response->last_name = ucwords(json_decode($response->response)->last_name);
                        }

                        break;

                    case 'roles';

                        $response->type = 8;
                        $response->module = $response->name;
                        $response->first_name = 'Super';
                        $response->last_name = 'Admin';

                        break;

                    case 'team_members';

                        $response->type = 9;
                        $response->module = $response->name;
                        $response->first_name = 'Super';
                        $response->last_name = 'Admin';

                        break;

                    case 'user_carriers';

                        $user = User::find($response->user_id);
                        $carrier = Carrier::find($response->carrier_id);

                        $response->type = 10;
                        $response->module = (!empty($carrier->name) ? $carrier->name : 'Module Deleted');
                        $response->first_name = (!empty($user->first_name) ? $user->first_name : '');
                        $response->last_name = (!empty($user->last_name) ? $user->last_name : '');

                        break;

                    case 'users';

                        $response->type = 11;
                        $response->module = 'Users table';

                        break;

                }

                $response->logId = $value->id;
                $response->statusType = $value->task_type;
                $response->status = $status;
                $response->table = $value->table_name;
                $modalData[$value->table_name][] = $response;
            }

        }

        $data = [
            'module' => $modalData,
        ];
        return view('admin.all_logs')->with('data', $data);

    }

    /*
     * A method to delete a log
     */
    public function deleteLog(Request $request)
    {
        $dataId = $request->get('dataId');
        $ChangeLogs = new ChangeLogs();
        $changeLog = $ChangeLogs->where('id', $dataId)->forceDelete();
        if ($changeLog) {
            return 1;
        } else {
            return 0;
        }
    }

    /*
     * A method to View details of a log
     */
    public function viewLog(Request $request)
    {

        $dataId = $request->get('dataId');
        $changeLog = ChangeLogs::where('id', $dataId)->first()->toJson();
        if ($changeLog) {
            return $changeLog;
        } else {
            return 0;
        }

    }

    /*
     * A method to delete all logs.
     */
    public function deleteAll()
    {
        $changeLog = ChangeLogs::truncate();
        if ($changeLog) {
            return 1;
        } else {
            return 0;
        }

    }

    /*
     * Method to delete responses
     */
    public function deleteResponse($id, $module_id)
    {
        $deleteResponse = Response::where('id', $id)->delete();
        $deleteModuleResponse = ModuleResponses::where('module_id', $module_id)->where('response_id', $id)->delete();
        return Response()->json(['status' => 1]);
    }

    /*
     * Method to update user password
     */
    public function updatePassword(Request $request)
    {
        $rules = [
            'password' => 'required|string|min:8'
        ];
        $array = [
            'password' => $request->password
        ];
        $validator = Validator::make($array, $rules);
        if ($validator->fails()) {
            return Response()->json(['status' => 0, 'message' => $validator->errors()->first()]);
        } else {
            try {
                User::where('id', $request->user_id)->update(['password' => bcrypt($request->password)]);
                return response()->json(['status' => 1, 'message' => 'Password updated successfully.']);
            } catch (\Exception $e) {
                return response()->json(['status' => 0, 'message' => 'Something went wrong, please try again later.']);
            }
        }
    }

    /*
     * Method to export all assigned users
     */
    public function exportUser($id = false)
    {
        if ($id) {
            $assignedUsers = \DB::table('user_module_roles')->select(['users.*', 'roles.role_name'])
                ->join('users', 'user_module_roles.user_id', '=', 'users.id')
                ->join('roles', 'user_module_roles.role_id', '=', 'roles.id')
                ->where('user_module_roles.module_id', $id)
                ->get();
            Excel::create('module_users', function ($excel) use ($assignedUsers) {
                $excel->sheet('Excel sheet', function ($sheet) use ($assignedUsers) {
                    $sheet->row(1, array(
                        'First Name', 'Last Name', 'Email', 'Phone', 'NPN', 'State', 'Created Date'
                    ));
                    $i = 2;
                    foreach ($assignedUsers as $user) {
                        $sheet->row($i, array(
                            $user->first_name, $user->last_name, $user->email, $user->phone, $user->npn, $user->state, $user->created_at
                        ));
                        $i++;
                    }
                });
            })->download('xls');
        } else {
            $assignedUsers = $this->users->getAllUsersWithDetails();
            Excel::create('users', function ($excel) use ($assignedUsers) {
                $excel->sheet('Excel sheet', function ($sheet) use ($assignedUsers) {
                    $sheet->row(1, array(
                        'First Name', 'Last Name', 'Email', 'Phone', 'NPN', 'State', 'Created Date'
                    ));
                    $i = 2;
                    foreach ($assignedUsers as $user) {
                        $sheet->row($i, array(
                            $user->first_name, $user->last_name, $user->email, $user->phone, $user->npn, $user->state, $user->created_at
                        ));
                        $i++;
                    }
                });
            })->download('xls');
        }
    }

    /*
     *  Method to get a form to load all users from a csv file.
     */
    public function importNewUser(Request $request)
    {
        ini_set('max_execution_time', '0');
        ini_set('max_input_time', '0');
        set_time_limit(0);

        $modules = $this->modules->getAllModules();
        $users = collect([
            'modules' => $modules
        ]);

        $dataArray = [];
        $inserted = [];
        $failed = [];

        $records = array();
        if (Input::file('datafile')) {
            /*-- upload file --*/
            $sheetdata = ExcelController::upload_excel(Input::file('datafile'))->toArray();
            for ($i = 0; $i < count($sheetdata); $i++) {
                $array = [

                    'first_name' => isset($sheetdata[$i]['first_name']) ? $sheetdata[$i]['first_name'] : '',
                    'last_name' => isset($sheetdata[$i]['last_name']) ? $sheetdata[$i]['last_name'] : '',
                    'phone' => isset($sheetdata[$i]['phone']) ? $sheetdata[$i]['phone'] : '',
                    'npn' => isset($sheetdata[$i]['npn']) ? $sheetdata[$i]['npn'] : '',
                    'email' => isset($sheetdata[$i]['email']) ? $sheetdata[$i]['email'] : '',
                    'state' => isset($sheetdata[$i]['state']) ? $sheetdata[$i]['state'] : '',
                    'password' => isset($sheetdata[$i]['password']) ? $sheetdata[$i]['password'] : '',

                    'carriers' => isset($sheetdata[$i]['carriers']) ? $sheetdata[$i]['carriers'] : '',
                    'gender' => isset($sheetdata[$i]['gender']) ? $sheetdata[$i]['gender'] : '',
                    'source' => isset($sheetdata[$i]['source']) ? $sheetdata[$i]['source'] : '',
                    'address' => isset($sheetdata[$i]['address']) ? $sheetdata[$i]['address'] : '',
                    'city' => isset($sheetdata[$i]['city']) ? $sheetdata[$i]['city'] : '',

                    'is_admin' => 0,
                ];
                $records[] = $array;
            }
            $dataArray = [
                'new' => $records,
                'inserted' => $inserted,
                'failed' => $failed
            ];
        } else {
            $results = $request->except('_token');
            $rules = [
                'first_name' => 'required',
                'last_name' => 'required',
                'email' => 'required|email|max:255|unique:users'
            ];

            for ($i = 0; $i < count($results['first_name']); $i++) {
                if ($results['first_name'][$i]) {
                    $array = [

                        'first_name' => isset($request['first_name'][$i]) ? $request['first_name'][$i] : '',
                        'last_name' => isset($request['last_name'][$i]) ? $request['last_name'][$i] : '',
                        'phone' => isset($request['phone'][$i]) ? $request['phone'][$i] : '',
                        'npn' => isset($request['npn'][$i]) ? $request['npn'][$i] : '',
                        'email' => isset($request['email'][$i]) ? $request['email'][$i] : '',
                        'state' => isset($request['state'][$i]) ? $request['state'][$i] : '',
                        'password' => isset($request['password'][$i]) ? $request['password'][$i] : '',

                        'carriers' => isset($request['carriers'][$i]) ? $request['carriers'][$i] : '',
                        'gender' => isset($request['gender'][$i]) ? $request['gender'][$i] : '',
                        'source' => isset($request['source'][$i]) ? $request['source'][$i] : '',
                        'address' => isset($request['address'][$i]) ? $request['address'][$i] : '',
                        'city' => isset($request['address'][$i]) ? $request['address'][$i] : '',

                        'is_admin' => 0,
                    ];
                    $validator = Validator::make($array, $rules);
                    if ($validator->fails()) {
                        $failed[] = $array;
                    } else {
                        User::create($array);
                        $inserted[] = $array;
                    }
                }

            }

            $dataArray = [
                'new' => [],
                'inserted' => $inserted,
                'failed' => $failed
            ];
        }
        return View('admin.import-user-form', compact('dataArray'))->with('users', $users);
    }

    /*
     * Method to export all responses of selected module
     */
    public function exportResponse($id = false)
    {
        $fields_array = [
            'PRI' => ['First Name', 'Middle Name', 'Last Name', 'Address', 'Apt#', 'City', 'Zip', 'Phone', 'Dob', 'Medicare#', 'Medicare Plan', 'Carrier', 'Physician Name', 'Comfort Health Office', 'Reason', 'Reason Detail', 'Company'],
            'contact_us' => ['Name', 'Email', 'Phone', 'Subject', 'State', 'message'],
            'event_module_page' => ['First Name', 'Last Name', 'Event Date', 'Time', 'Phone', 'NPN', 'Attendees', 'Email', 'State'],
            'web_page' => ['First Name', 'Last Name', 'Phone', 'Email', 'State', 'Sub Domain']
        ];
        if ($id) {
            $module_name = Module::where('id', $id)->first(['file_name', 'name']);
            $response = $this->responses->getAllResponses($id);
            $header_row = $fields_array[$module_name->file_name];
            $module_type = $module_name->file_name;

            Excel::create('' . $module_name->name . '_responses', function ($excel) use ($response, $header_row, $module_type) {
                $excel->sheet('Excel sheet', function ($sheet) use ($response, $header_row, $module_type) {
                    $sheet->row(1, $header_row);
                    $i = 2;
                    if ($module_type == 'event_module_page') {
                        foreach ($response as $item) {
                            $sheet->row($i, array(
                                isset(json_decode($item->response)->first_name) ? json_decode($item->response)->first_name : "",
                                isset(json_decode($item->response)->last_name) ? json_decode($item->response)->last_name : "",
                                isset(json_decode($item->response)->date) ? json_decode($item->response)->date : "",
                                isset(json_decode($item->response)->time) ? json_decode($item->response)->time : "",
                                isset(json_decode($item->response)->phone) ? json_decode($item->response)->phone : "",
                                isset(json_decode($item->response)->npn) ? json_decode($item->response)->npn : "",
                                isset(json_decode($item->response)->attendees) ? json_decode($item->response)->attendees : "",
                                isset(json_decode($item->response)->email) ? json_decode($item->response)->email : "",
                                isset(json_decode($item->response)->state) ? json_decode($item->response)->state : ""
                            ));
                            $i++;
                        }
                    }
                    if ($module_type == 'web_page') {
                        foreach ($response as $item) {
                            $sheet->row($i, array(
                                isset(json_decode($item->response)->first_name) ? json_decode($item->response)->first_name : "",
                                isset(json_decode($item->response)->last_name) ? json_decode($item->response)->last_name : "",
                                isset(json_decode($item->response)->phone) ? json_decode($item->response)->phone : "",
                                isset(json_decode($item->response)->email) ? json_decode($item->response)->email : "",
                                isset(json_decode($item->response)->state) ? json_decode($item->response)->state : "",
                                (!empty(json_decode($item->response)->subdomain) ? json_decode($item->response)->subdomain : json_decode($item->response)->first_name . '' . json_decode($item->response)->last_name)
                            ));
                            $i++;
                        }
                    }
                    if ($module_type == 'contact_us') {
                        foreach ($response as $item) {
                            $sheet->row($i, array(
                                isset(json_decode($item->response)->name) ? json_decode($item->response)->name : "",
                                isset(json_decode($item->response)->email) ? json_decode($item->response)->email : "",
                                isset(json_decode($item->response)->phone) ? json_decode($item->response)->phone : "",
                                isset(json_decode($item->response)->subject) ? json_decode($item->response)->subject : "",
                                isset(json_decode($item->response)->state) ? json_decode($item->response)->state : "",
                                isset(json_decode($item->response)->message) ? json_decode($item->response)->message : ""
                            ));
                            $i++;
                        }
                    }
                    if ($module_type == 'PRI') {
                        foreach ($response as $item) {
                            $sheet->row($i, array(
                                json_decode($item->response)->first_name, json_decode($item->response)->middle_name, json_decode($item->response)->last_name, json_decode($item->response)->address, json_decode($item->response)->apt_number, json_decode($item->response)->city, json_decode($item->response)->zip_code, json_decode($item->response)->phone, json_decode($item->response)->dob, json_decode($item->response)->medicare_number, json_decode($item->response)->medicare_plan_name, json_decode($item->response)->carrier, json_decode($item->response)->physician_name, json_decode($item->response)->comfort_health_office, json_decode($item->response)->reason, json_decode($item->response)->reason_detail, json_decode($item->response)->company_name
                            ));
                            $i++;
                        }
                    }
                });
            })->download('xls');
        } else {
            return redirect('/');
        }
    }

    /*
     * Method to list all users on admin user page
     */
    public function listAllUser(Request $request)
    {
        if ($request->user_id != '') {
            $deleteUser = User::destroy($request->user_id);
            $deleteRole = UserModuleRole::where('user_id', $request->user_id)->delete();
            $deleteNotification = Notification::where('user_id', $request->user_id)->forceDelete();
            $response_ids = DB::table('responses')->select(DB::raw('GROUP_CONCAT(id) as ids'))->where('user_id', $request->user_id)->first();
            $deleteResponse = Response::where('user_id', $request->user_id)->delete();
            $deleteModuleResponse = ModuleResponses::whereIn('response_id', explode(',', $response_ids->ids))->delete();
        }
        if ($request->search != '') {
            $indexes = " first_name LIKE '%" . $request->search . "%' OR last_name LIKE '%" . $request->search . "%' OR carriers LIKE '%" . $request->search . "%' OR email LIKE '%" . $request->search . "%' OR state LIKE '%" . $request->search . "%'";
            $assignedUsers = User::where('is_admin', '!=', '1')->whereRaw($indexes)->paginate(10);
            $reset_link = 1;
        } else {
            $assignedUsers = User::where('is_admin', '!=', '1')->paginate(10);
            $reset_link = '';
        }
        $users = collect([
            'assignedUsers' => $assignedUsers,
            'reset_link' => $reset_link,
        ]);
        if ($request->ajax()) {
            return response()->json(view('admin.assigned_user_table', compact('users'))->render());
        }
        return view('admin.users')->with('users', $users);
    }

}
