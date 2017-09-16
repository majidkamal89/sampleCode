<?php

namespace App\Http\Controllers;

use App\Headline;
use App\Http\Requests\UserLoginRequest;
use App\Http\Requests\UserRegisterationRequest;
use App\MainImage;
use App\Module;
use App\Response;
use App\User;
use App\UserModuleRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use App\EmailTemplate;

class UserController extends Controller
{
    function __construct(){
//        parent::__construct();
        $this->user = new User();
        $this->userModuleRole = new UserModuleRole();
        $this->module = new Module();
        $this->response = new Response();
    }

    public function register(UserRegisterationRequest $request){
        $user = $this->user->register($request);
        if($user['status'] == 'success'){
            session()->flash('success', 'Account Created Successfully!');
            return redirect()->to('/account')->with('success', 'Account Created Successfully!');
        } else {
            session()->flash('failure', 'Error creating account! ' . $user['message']);
            return redirect()->back()->with('failure', 'Error creating account! ' . $user['message']);
        }
    }

    public function login(UserLoginRequest $request){
        Auth::attempt(['email' => $request['email'], 'password' => $request['password']]);
        if(Auth::user()){
            if(Auth::user()->is_admin == 1){
                return redirect('/');
            } else if(Auth::user()->hasRole('admin') == true) {
                Auth::user()['mod_admin'] = true;
            } else {
                Auth::user()['mod_admin'] = false;
            }
            if(Auth::user()->password_reset == 0) {
                return redirect('/password-reset');
            }
            return redirect('/account');
        } else {
            session()->flash('error', 'Invalid email or password!');
            return Redirect::to('/login');
        }
    }

    public function logout(){
        Auth::logout();
        return redirect('/');
    }

    public function userModule($moduleId,$userID=false){
        if(Auth::user()){
            $module = $this->module->getSpecificModule($moduleId);
            $user_id = ($userID) ? $userID:Auth::user()->id;
            $responses = \DB::table('responses')
                ->select(['responses.*','modules.name','modules.id as moduleID','users.first_name','users.last_name'])
                ->join('module_responses', 'responses.id', '=', 'module_responses.response_id')
                ->join('modules', 'module_responses.module_id', '=', 'modules.id')
                ->join('users', 'responses.user_id', '=', 'users.id')
                ->where('modules.id', $moduleId)->where('user_id', $user_id)->get();

            foreach($responses as $key => $val){
                $responses[$key]->module_name = $val->name;
                $responses[$key]->user_name = $val->first_name.' '.$val->last_name;
            }
            $headlines = Headline::get();
            $main_images = MainImage::get();
            session()->forget(['success', 'error']);
            return view('modules.module', compact('responses','headlines','main_images'))->with('formName', $module);
        }
        return Redirect::to('/');
    }

    public function submitForm(Request $request){
        if(!Auth::user() && (!empty($request->get('action')) && $request->get('action') == 'web_page')){
            $domain_exist = $this->domainCheck($request->subdomain);
            $last_name = 'Last name field is required.';
            if($domain_exist){
                $last_name = 'Domain name already exist.';
                $request->request->add(['last_name' => '']);
            }

            $rule = [
                'profilepic' => 'image',
                'email' => 'required|string|email|unique:users',
                'first_name' => 'required',
                'last_name' => 'required',
                'phone' => 'required',
                'npn' => 'required',
                'state' => 'required',
            ];
            $message = [
                'profilepic.image' => 'Profile picture must be an Image.',
                'last_name.required' => $last_name
            ];
            $validator = Validator::make($request->all(), $rule, $message);
            if($validator->fails()){
                return Redirect::back()->withInput($request->all())->withErrors($validator);
            }
            $dataArray = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'npn' => $request->npn,
                'email' => $request->email,
                'state' => $request->state,
                'password' => bcrypt($request->subdomain),
                'is_admin' => 0,
            ];
            $user_id = User::create($dataArray);
            $request->request->add(['user_id' => $user_id->id]);

            $data = [
                'user_id' => $user_id->id,
                'module_id' => $request->module_id,
                'role_id' => 2
            ];
            $result = UserModuleRole::create($data);
        }
        if(!empty($request->file('profilepic'))){
            $file = array('profilepic' => $request->file('profilepic'));
            $destinationPath = base_path('public/uploads/webpage/'); // upload path
            $extension = $file['profilepic']->getClientOriginalExtension(); // getting image extension
            $fileName = strtotime(date('Y-m-d h:i:s')).'-'.$file['profilepic']->getClientOriginalName(); // renameing image
            $file['profilepic']->move($destinationPath, $fileName); // uploading file to given path
            $request->request->add(['profile_picture' => $fileName]);
        }
        if(empty($request->file('profilepic')) && !empty($request->old_profile_picture)){
            $request->request->add(['profile_picture' => $request->old_profile_picture]);
        }
        if(!empty($request->id)){
            $domain_exist = $this->domainCheck($request->subdomain,$request->id);
            $last_name = 'Last name field is required.';
            if($domain_exist){
                $last_name = 'Domain name already exist.';
                $request->request->add(['last_name' => '']);
            }
            $rule = [
                'email' => 'required|email',
                'first_name' => 'required',
                'last_name' => 'required',
                'phone' => 'required',
                'npn' => 'required',
                'state' => 'required',
            ];
            $message = [
                'last_name.required' => $last_name
            ];
            $validator = Validator::make($request->all(), $rule, $message);
            if($validator->fails()){
                return Redirect::back()->withInput($request->all())->withErrors($validator);
            }
        }
        $response = $this->response->submitForm($request);
        if(!empty($request->subdomain)){
            $notification = !empty($request->id) ? 'Form Updated Successfully! Your Domain name is '.$request->subdomain:'Form Submitted Successfully! Your Domain name is '.$request->subdomain;
        } else {
            $notification = 'Form Submitted Successfully!';
        }
        if(!empty($request->form_name) && $request->form_name == 'PRI'){
            $email_template = EmailTemplate::where('template_type', 8)->first();
            if(count($email_template) > 0){
                $this->template = new EmailTemplate();
                $message = $this->template->build([
                    'first_name' => $request->first_name,
                    'last_name' => (!empty($request->last_name) ? $request->last_name:$request->middle_name),
                ],8);
                $subject = $email_template->subject;
                $this->response->sendEmail(Auth::user()->email,$subject,$message);
            } else {
                $this->response->sendEmail(Auth::user()->email,'Carefree Agency','Thank you for submitting PRI on carefreeagency.com.');
            }
        }
        if($response['status'] == 'success'){
            if(Auth::user() && (!empty(Auth::user()->is_admin) && Auth::user()->is_admin == 1)){
                return redirect()->route('moduleDetail', $request->module_id)->with('success', $notification);
            }
            return redirect()->route('userFormModule', $request->module_id)->with('success', $notification);
        } else {
            return redirect()->route('userFormModule', $request->module_id)->with('failure', 'Error submitting form! ' . $response['message']);
        }
    }
    // Method to fetch all form data and list on confirmation page before save in DB
    public function confirmation(Request $request){
        return view('confirmation')->with('formData', $request->all());
    }

    // Method to display user response details on front-end
    public function userResponseDetail($moduleId,$id){
        if($id){
            $response = $this->response->getResponseWithDetails($id);
            $userName = User::where('id', $response->user_id)->first(['first_name','last_name']);
            $data = [
                'response' => $response
            ];
            $this->module->file_name = 'response_detail';
            $this->module->id = $moduleId;
            return view('modules.module_response_view', compact('data'))->with('formName', $this->module)->with('submitBy', ''.$userName->first_name.' '.$userName->last_name.'');
        } else {
            return \redirect('/404');
        }
    }

    /*
     * Method to validate sub domain name
     */
    public function validateDomain(Request $request){
        $responses = Response::where('module_type', 1)->where('id', '!=', $request->id)->get();
        foreach($responses as $response){
            if(strtolower(json_decode($response->response)->first_name) == strtolower($request->first_name) && strtolower(json_decode($response->response)->last_name) == strtolower($request->last_name)){
                echo 1;exit;
            }
        }
        echo 0;
    }

    /*
     * Method to display web page
     */
    public function webpageform($domain){
        $responses = Response::where('module_type', 1)->get();
        foreach($responses as $response){
            if(!empty(json_decode($response->response)->subdomain) && strtolower(json_decode($response->response)->subdomain) == strtolower($domain)){
                return view('home.mywebpage')->with('data', $response);
            }
        }
        session()->forget(['success', 'error']);
        return Redirect::to('/');
    }

    /*
     * Method to create web page for client
     */
    public function mywebpage(){
        $module = Module::where('file_name', 'web_page')->where('privilege', 1)->first();
        if($module && !Auth::user()){
            $module = $this->module->getSpecificModule($module->id);
            $responses = \DB::table('responses')
                ->select(['responses.*','modules.name','modules.id as moduleID','users.first_name','users.last_name'])
                ->join('module_responses', 'responses.id', '=', 'module_responses.response_id')
                ->join('modules', 'module_responses.module_id', '=', 'modules.id')
                ->join('users', 'responses.user_id', '=', 'users.id')
                ->where('modules.id', $module->id)->where('user_id', 0)->get();

            foreach($responses as $key => $val){
                $responses[$key]->module_name = $val->name;
                $responses[$key]->user_name = $val->first_name.' '.$val->last_name;
            }
            $headlines = Headline::get();
            $main_images = MainImage::get();
            return view('modules.module', compact('responses','headlines','main_images'))->with('formName', $module);
        }
        return Redirect::to('/');
    }

    /*
     * Method to validate domain name on back-end if fron-end validation fails
     */
    public function domainCheck($domain,$id=false){
        if($id){
            $responses = Response::where('module_type', 1)->where('id', '!=', $id)->get();
        } else {
            $responses = Response::where('module_type', 1)->get();
        }
        foreach($responses as $response){
            if(!empty(json_decode($response->response)->subdomain) && strtolower(json_decode($response->response)->subdomain) == strtolower($domain)){
                return true;
            }
        }
        return false;
    }

    /*
     * Method to send Appointment request email
     */
    public function sendAppointment(Request $request){
        $data = '';
        $responses = Response::where('module_type', 1)->get();
        foreach($responses as $response){
            if(!empty(json_decode($response->response)->subdomain) && json_decode($response->response)->subdomain == $request->subdomain){
                $data = $response;
            }
        }
        $today_date = date('Y-m-d');
        $rules = [
            'name' => 'required|min:3',
            'email' => 'required|email',
            'phone' => 'required',
            'message' => 'required',
            'appointment_date' => 'required|date|date_format:Y-m-d|after:'.$today_date,
        ];
        $validator = Validator::make($request->all(), $rules);
        session()->forget(['success','error']);
        if($validator->fails()){
            session()->flash('error', 'Please fix all required field issues.');
            return redirect()->back()->withInput($request->all())->withErrors($validator)->with('data', $data);
        }
        $client_email = '<br/>Email from visitor '.$request->email;

        session()->flash('success', 'Your Appointment request is submitted.');
        $result = $this->response->sendEmail(['address' => ['email' => $request->toEmail]],'Appointment Request',$request->message.''.$client_email);
        return redirect()->back()->with('data', $data);
    }
}
