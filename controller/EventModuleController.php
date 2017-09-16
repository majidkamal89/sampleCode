<?php

namespace App\Http\Controllers;

use App\Module;
use App\Response;
use App\Role;
use App\UserModuleRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use App\EmailTemplate;
use Illuminate\Support\Facades\Input;

class EventModuleController extends Controller
{
    public function __construct()
    {
        $this->response = new Response();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $users = DB::table('user_module_roles')
            ->select('user_module_roles.user_id')
            ->join('users', 'user_module_roles.user_id', '=', 'users.id')
            ->join('roles', 'user_module_roles.role_id', '=', 'roles.id')
            ->where('roles.role_name', 'admin')
            ->groupBy('user_module_roles.user_id')
            ->get();
        $roles = Role::where('role_name', 'admin')->get();
        $events = Module::where('event_module', 1)->get();

        foreach($events as $key => $val){
            $start_time = json_decode($val->start_time);
            $end_time = json_decode($val->end_time);
            
            if (!empty($val->start_date)){
                $start_date = explode('|', $val->start_date);
                $events[$key]->event_start_date = $start_date[0];
                $date_key = $start_date[0];

                foreach($start_time->$date_key as $k => $v){
                    if($k == 0){
                        $events[$key]->event_start_time = (!empty($v)) ? $v:'';
                    }
                }
                foreach($end_time->$date_key as $k => $v){
                    if($k == 0){
                        $events[$key]->event_end_time = (!empty($v)) ? $v:'';
                    }
                }

                $custom_time = '';
                if(!empty($start_time->$date_key) && !empty($end_time->$date_key)){
                    for($i=1; $i<count($start_time->$date_key); $i++){
                        foreach($start_time->$date_key as $k1 => $v1){
                            if($k1 == $i){
                                $custom_time .= '<div class="time_row"><div class="col-md-3"></div><div class="col-sm-3 padding-0">
                        <input type="text" name="event_start_time[]" id="start_time" value="'.$v1.'" class="form-control time-masked masked" data-format="99:99:99" data-placeholder="_" placeholder="23:59:59">
                        </div><div class="col-sm-1 text-center">';
                            }
                        }
                        foreach($end_time->$date_key as $k1 => $v1){
                            if($k1 == $i){
                                $custom_time .= '<label class="control-label">To</label></div>
                        <div class="col-sm-3 padding-0"><input type="text" name="event_end_time[]" id="end_time" value="'.$v1.'" class="form-control time-masked masked" data-format="99:99:99" data-placeholder="_" placeholder="23:59:59">
                        </div><div class="col-sm-2 text-center"><a href="javascript:;" class="btn btn-danger btn-small btn-xs delete-time" style="margin: 10px 0px 0px 0px;">
                        <span class="glyphicon glyphicon-minus"></span></a></div></div>';
                            }
                        }
                    }
                }
                $events[$key]->custom_time_first = $custom_time;

                $custom_date = '';
                for($j=1; $j<count($start_date); $j++){
                    $date_key1 = $start_date[$j];

                    $custom_date .= '<div class="date_row"><div class="form-group"><label class="control-label col-sm-3">
<a href="javascript:;" class="btn btn-danger btn-small btn-xs delete-date" style="margin: 0 5px;">
<span class="glyphicon glyphicon-minus"></span></a></label><div class="col-sm-9">
<input type="text" name="start_date[]" class="form-control date-masked masked" value="'.$date_key1.'" data-format="9999-99-99" data-placeholder="_" placeholder="YYYY-MM-DD">
</div></div>';
                    if(!empty($start_time->$date_key1) && !empty($end_time->$date_key1)){

                        for($k=0; $k<count($start_time->$date_key1); $k++){
                            if($k == 0){
                                foreach($start_time->$date_key1 as $key2 => $val2){
                                    if($k == $key2){
                                        $custom_date .= '<div class="form-group"><div class="col-md-3"></div>
<div class="col-md-9 margin-0 time_section"><label class="control-label col-sm-3">
<a href="javascript:;" class="btn btn-info btn-small btn-xs add-mutiple-time" data-key="'.$j.'" style="margin: 0px;">
<span class="glyphicon glyphicon-plus"></span></a>Time:</label>
<div class="col-sm-3 padding-0">
<input type="text" name="event_start_time'.$j.'[]" value="'.$val2.'" class="form-control time-masked masked" data-format="99:99:99" data-placeholder="_" placeholder="23:59:59"></div>
<div class="col-sm-1 text-center">';
                                    }
                                }
                                foreach($end_time->$date_key1 as $key2 => $val2){
                                    if($k == $key2){
                                        $custom_date .= '<label class="control-label">To</label></div><div class="col-sm-3 padding-0">
<input type="text" name="event_end_time'.$j.'[]" value="'.$val2.'" class="form-control time-masked masked" data-format="99:99:99" data-placeholder="_" placeholder="23:59:59"></div>';
                                    }
                                }
                            } else {
                                foreach($start_time->$date_key1 as $key3 => $val3){
                                    if($k == $key3){
                                        $custom_date .= '<div class="time_row"><div class="col-md-3"></div><div class="col-sm-3 padding-0">
                        <input type="text" name="event_start_time'.$j.'[]" id="start_time" value="'.$val3.'" class="form-control time-masked masked" data-format="99:99:99" data-placeholder="_" placeholder="23:59:59">
                        </div><div class="col-sm-1 text-center">';
                                    }
                                }
                                foreach($end_time->$date_key1 as $key3 => $val3){
                                    if($k == $key3){
                                        $custom_date .= '<label class="control-label">To</label></div>
                        <div class="col-sm-3 padding-0">
                        <input type="text" name="event_end_time'.$j.'[]" id="end_time" value="'.$val3.'" class="form-control time-masked masked" data-format="99:99:99" data-placeholder="_" placeholder="23:59:59">
                        </div><div class="col-sm-2 text-center"><a href="javascript:;" class="btn btn-danger btn-small btn-xs delete-time" style="margin: 10px 0px 0px 0px;">
                        <span class="glyphicon glyphicon-minus"></span></a></div></div></div></div></div>';
                                    }
                                }
                            }
                        }

                    } else {
                        $custom_date .= '<div class="form-group"><div class="col-md-3"></div>
<div class="col-md-9 margin-0 time_section"><label class="control-label col-sm-3">
<a href="javascript:;" class="btn btn-info btn-small btn-xs add-mutiple-time" data-key="'.$j.'" style="margin: 0px;">
<span class="glyphicon glyphicon-plus"></span></a>Time:</label>
<div class="col-sm-3 padding-0">
<input type="text" name="event_start_time'.$j.'[]" value="" class="form-control time-masked masked" data-format="99:99:99" data-placeholder="_" placeholder="23:59:59"></div>
<div class="col-sm-1 text-center"><label class="control-label">To</label></div><div class="col-sm-3 padding-0">
<input type="text" name="event_end_time'.$j.'[]" value="" class="form-control time-masked masked" data-format="99:99:99" data-placeholder="_" placeholder="23:59:59"></div>';
                    }
                }
                $events[$key]->custom_date_first = $custom_date;
            }
        }

        if ($request->ajax()) {
            return response()->json(view('admin.events_table', compact('events', 'users', 'roles'))->render());
        }
        return view('admin.events', compact('events', 'users', 'roles'));
    }

    /**
     * Store a event module page response.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function create($name, Request $request)
    {
        $rules = [
            "first_name" => "required|min:3",
            "last_name" => "required|min:3",
            "phone" => "required",
            "npn" => "required",
            "attendees" => "required",
            "email" => "required|email",
            "state" => "required",
            "agree_to_term" => "required"
        ];
        $message = [
            "first_name.required" => "First name field is required.",
            "last_name.required" => "Last name field is required.",
            "npn.required" => "License # field is required.",
            "attendees.required" => "Attendees field is required."
        ];
        $event = Module::where('name', $name)->first();
        $validator = Validator::make($request->all(), $rules, $message);
        if($validator->fails()){
            return view('home.event_module', compact('event'))->withInput($request->all())->withErrors($validator);
        }
        $response = $this->response->submitForm($request);
        $email_template = EmailTemplate::where('template_type', 6)->first();
        if(count($email_template) > 0){
            $this->template = new EmailTemplate();
            $message = $this->template->build([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'npn' => $request->npn,
                'date' => !empty($request->date) ? $request->date:'',
                'time' => !empty($request->time) ? $request->time:'',
                'attendees' => $request->attendees,
                'state' => $request->state,
                'email' => $request->email,
                'office_address' => $request->office_address
            ],6);
            $subject = $email_template->subject;
            $this->response->sendEmail($request->email,$subject,$message);
        } else {
            $this->response->sendEmail($request->email,'Carefree Agency','Thank you for submitting RSVP on carefreeagency.com.');
        }

        if($response['status'] == 'success'){
            return redirect()->route('viewEvent', $name)->with('success', 'Form submitted successfully.')
                ->with('pixel_tracker', $event->pixel_tracker);
        } else {
            return redirect()->route('viewEvent', $name)->with('failure', 'Error submitting form! ' . $response['message'])
                ->with('pixel_tracker', $event->pixel_tracker);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!empty($request->id)) {
            $alreadyExist = Module::where('name', $request->name)->where('id', '!=', $request->id)->first();
        } else {
            $alreadyExist = Module::where('name', $request->name)->first();
        }
        $errorMessage = 'Event name field is required.';
        if (count($alreadyExist) > 0) {
            $request->request->add(['name' => '']);
            $errorMessage = 'Event name already exist.';
        }
        $rules = [
            'name' => 'required|min:3',
            'event_content' => 'required|min:20',
            'template' => 'required',
        ];

        $start_time = $end_time = [];
        foreach($request->start_date as $key => $val){
            $request->request->add([''.$val.'_start_date' => $val]);
            $rules[''.$val.'_start_date'] = 'string|nullable|date:Y-m-d';
            if($key == 0){
                foreach($request->event_start_time as $key0 => $time0){
                    if($key0 == 0){
                        $request->request->add([''.$val.'_start_time' => $time0]);
                        if(!empty($time0)){
                            $rules[''.$val.'_end_time'] = 'required|string|date_format:H:i:s|after:'.$val.'_start_time';
                        }
                    } else {
                        $request->request->add([''.$val.'_start_time'.$key0 => $time0]);
                        $rules[''.$val.'_start_time'.$key0.''] = 'string|nullable|date_format:H:i:s';
                        if(!empty($time0)){
                            $rules[''.$val.'_end_time'.$key0.''] = 'required|string|date_format:H:i:s|after:'.$val.'_start_time'.$key0.'';
                        }
                    }
                }
                foreach($request->event_end_time as $key1 => $time1){
                    if($key1 == 0){
                        $request->request->add([''.$val.'_end_time' => $time1]);
                    } else {
                        $request->request->add([''.$val.'_end_time'.$key1 => $time1]);
                    }
                }
                $start_time[$val] = $request->event_start_time;
                $end_time[$val] = $request->event_end_time;
            } else {
                $st_time = 'event_start_time'.$key;
                $en_time = 'event_end_time'.$key;
                if(!empty($request->$st_time)) {
                    foreach ($request->$st_time as $key0 => $time0) {
                        if ($key0 == 0) {
                            $request->request->add([''.$val.'_start_time' => $time0]);
                            if (!empty($time0)) {
                                $rules[''.$val.'_end_time'] = 'required|string|date_format:H:i:s|after:'.$val.'_start_time';
                            }
                        } else {
                            $request->request->add([''.$val.'_start_time' . $key0 => $time0]);
                            $rules[''.$val.'_start_time' . $key0 . ''] = 'string|nullable|date_format:H:i:s';
                            if (!empty($time0)) {
                                $rules[''.$val.'_end_time' . $key0 . ''] = 'required|string|date_format:H:i:s|after:'.$val.'_start_time' . $key0 . '';
                            }
                        }
                    }
                }
                if(!empty($request->$en_time)){
                    foreach($request->$en_time as $key1 => $time1){
                        if($key1 == 0){
                            $request->request->add([''.$val.'_end_time' => $time1]);
                        } else {
                            $request->request->add([''.$val.'_end_time'.$key1 => $time1]);
                        }
                    }
                }
                $start_time[$val] = $request->$st_time;
                $end_time[$val] = $request->$en_time;
            }
        }


        $message = [
            'template.required' => 'Template field is required.',
            'event_content.required' => 'Event content is required.',
            'name.required' => $errorMessage
        ];
        $validator = Validator::make($request->all(), $rules, $message);
        if ($validator->fails()) {
            return Response()->json(['status' => 0, 'message' => $validator->errors()->first()]);
        }

        try {
            // File upload
            $image = '';
            if ($request->file('event_image')) {
                $file = array('event_image' => $request->file('event_image'));
                $destinationPath = base_path('public/uploads/events/'); // upload path
                $extension = $file['event_image']->getClientOriginalExtension(); // getting image extension
                $fileName = strtotime(date('Y-m-d h:i:s')) . '-' . $file['event_image']->getClientOriginalName(); // renameing image
                $file['event_image']->move($destinationPath, $fileName); // uploading file to given path
                $image = $fileName;
            }
            // End File Upload

            if (!empty($request->id)) {
                $old_image = Module::where('id', $request->id)->first(['event_image']);
                $dataArray = [
                    'name' => $request->name,
                    'event_content' => $request->event_content,
                    'template' => $request->template,
                    'pixel_tracker' => $request->pixel_tracker,
                    'created_at' => date('Y-m-d h:i:s'),
                    'updated_at' => date('Y-m-d h:i:s'),
                    'event_image' => (!empty($image)) ? $image : $old_image->event_image,
                    'privilege' => $request->privilege,
                    'file_name' => 'event_module_page',
                    'start_date' => (!empty($request->start_date)) ? implode('|', $request->start_date):null,
                    /*'end_date' => (!empty($request->end_date)) ? $request->end_date:null,*/
                    'start_time' => json_encode($start_time),
                    'end_time' => json_encode($end_time),
                    'office_address' => (!empty($request->office_address)) ? $request->office_address:'',
                ];
                $update = Module::find($request->id)->update($dataArray);
                if (!empty($image) && !empty($old_image->event_image)) {
                    if (file_exists(base_path('public/uploads/events/') . $old_image->event_image)) {
                        unlink(base_path('public/uploads/events/') . $old_image->event_image);
                    }
                }
                return response()->json(['status' => 1, 'message' => 'Event updated successfully.']);
            }
            $dataArray = [
                'name' => $request->name,
                'event_content' => $request->event_content,
                'template' => $request->template,
                'pixel_tracker' => $request->pixel_tracker,
                'created_at' => date('Y-m-d h:i:s'),
                'updated_at' => date('Y-m-d h:i:s'),
                'event_image' => $image,
                'event_module' => 1,
                'privilege' => $request->privilege,
                'file_name' => 'event_module_page',
                'start_date' => (!empty($request->start_date)) ? implode('|', $request->start_date):null,
                /*'end_date' => (!empty($request->end_date)) ? $request->end_date:null,*/
                'start_time' => json_encode($start_time),
                'end_time' => json_encode($end_time),
                'office_address' => (!empty($request->office_address)) ? $request->office_address:'',
            ];
            $save = Module::create($dataArray);
            return response()->json(['status' => 1, 'message' => 'Event created successfully.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Something went wrong, please try again later.']);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  string $name
     * @return \Illuminate\Http\Response
     */
    public function show($name)
    {
        $event = Module::where('name', $name)->take(1)->get();
        if (count($event) > 0) {
            $time_array = [];
            foreach($event as $key => $val){
                $start_date = explode('|', $val->start_date);
                $event[$key]->start_date = $start_date;
                if(!empty($val->start_time) && !empty($val->end_time)){
                    $st_time = json_decode($val->start_time);
                    $en_time = json_decode($val->end_time);
                    for($i=0; $i<count($start_date); $i++){
                        $time_row = '';
                        foreach($st_time as $st_key => $st_val){
                            if($st_key == $start_date[$i]){
                                if(!empty($st_val)){
                                    foreach($st_val as $key1 => $time){
                                        if(!empty($time)){
                                            $time_row .= date('g:i a', strtotime($time)).' To _'.$key1.'|';
                                        }
                                    }
                                }
                            }
                        }
                        foreach($en_time as $en_key => $en_val){
                            if($en_key == $start_date[$i]){
                                if(!empty($en_val)){
                                    foreach($en_val as $key1 => $time){
                                        if(!empty($time)){
                                            $time_row = str_replace('_'.$key1.'', date('g:i a', strtotime($time)).'', $time_row);
                                            $time_row = rtrim($time_row, '|');
                                        }
                                    }
                                }
                            }
                        }
                        $time_array[$start_date[$i]] = $time_row;
                    }
                }
                $event[$key]->event_time_row = $time_array;
            }
            return view('home.event_module', compact('event'));
        }
        return Redirect::to('/');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Method to fetch event time.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function getEventTime($id, Request $request)
    {
        $event_detail = Module::where('id', $id)->first(['id','start_time','end_time']);
        $html = 'No record found.';
        if($event_detail->start_time != ''){
            $st_time = json_decode($event_detail->start_time);
            $en_time = json_decode($event_detail->end_time);
            $html = '<label for="">Event Time *</label><select class="form-control pointer required" name="time" required="required">
<option value=""> -- Select a Time --</option>';
            for($i=0; $i<count($st_time); $i++){
                $time_row = '';
                foreach($st_time as $key => $st_val){
                    if($request->search_key == $key){
                        if(!empty($st_val)){
                            foreach($st_val as $key => $time){
                                if(!empty($time)) {
                                    $time_row .= date('g:i a', strtotime($time)) . ' To _' . $key . '|';
                                }
                            }
                        }
                    }
                }
                foreach($en_time as $key => $en_val){
                    if($request->search_key == $key){
                        if(!empty($en_val)){
                            foreach($en_val as $key => $time){
                                if(!empty($time)) {
                                    $time_row = str_replace('_' . $key . '', date('g:i a', strtotime($time)) . '', $time_row);
                                    $time_row = rtrim($time_row, '|');
                                }
                            }
                        }
                    }
                }
                $time_array = explode('|', $time_row);
                for($j=0; $j<count($time_array); $j++){
                    $html .= '<option value="'.(($time_array[$j] == 0) ? '12:00 pm To 1:00 pm':$time_array[$j]).'">'.(($time_array[$j] == 0) ? '12:00 pm To 1:00 pm':$time_array[$j]).'</option>';
                }
            }
            $html .= '</select>';
        }
        echo $html;
        exit;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->module = new Module();
        $delete = Module::find($id);
        $this->module->destroy($id);
        $delete_roles = UserModuleRole::where('module_id', $id)->delete();
        if (file_exists(base_path('public/uploads/events/') . $delete->event_image)) {
            unlink(base_path('public/uploads/events/') . $delete->event_image);
        }
        return response()->json(['status' => 1]);
    }

    /*
     * Method to display events page with all events
     */
    public function listAllEvents()
    {
        $event = Module::where('file_name', 'event_module_page')->where('privilege', 0)->get();
        if (count($event) > 0) {
            foreach ($event as $key => $val) {
                $time_array = [];
                $start_date = explode('|', $val->start_date);
                $event[$key]->start_date = $start_date;
                if (!empty($val->start_time) && !empty($val->end_time)) {
                    $st_time = json_decode($val->start_time);
                    $en_time = json_decode($val->end_time);
                    for ($i = 0; $i < count($start_date); $i++) {
                        $time_row = '';
                        foreach ($st_time as $st_key => $st_val) {
                            if ($st_key == $start_date[$i]) {
                                if (!empty($st_val)) {
                                    foreach ($st_val as $key1 => $time) {
                                        if (!empty($time)) {
                                            $time_row .= date('g:i a', strtotime($time)) . ' To _' . $key1 . '|';
                                        }
                                    }
                                }
                            }
                        }
                        foreach ($en_time as $en_key => $en_val) {
                            if ($en_key == $start_date[$i]) {
                                if (!empty($en_val)) {
                                    foreach ($en_val as $key1 => $time) {
                                        if (!empty($time)) {
                                            $time_row = str_replace('_' . $key1 . '', date('g:i a', strtotime($time)) . '', $time_row);
                                            $time_row = rtrim($time_row, '|');
                                        }
                                    }
                                }
                            }
                        }
                        $time_array[$start_date[$i]] = $time_row;
                    }
                }
                $event[$key]->event_time_row = $time_array;
            }
        }
        return view('home.all_events', compact('event'));
    }

    /*
     * Method to preview template on for events page
     */
    public function previewTemplate(){
        $template = ['page_header' => '', 'name' => ''];
        if(Input::get('template') == 'miami_dade'){
            $template['page_header'] = 'Template1';
            $template['name'] = Input::get('template');
        }
        if(Input::get('template') == 'patriots'){
            $template['page_header'] = 'Template2';
            $template['name'] = Input::get('template');
        }
        return view('home.template_preview', compact('template'));

    }
}
