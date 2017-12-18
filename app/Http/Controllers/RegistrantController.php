<?php

namespace App\Http\Controllers;

use App\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests;
use App\Registrant;
use App\Course;
use App\Rating;
use DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;

class RegistrantController extends Controller
{
    public function Registrant_validation(Request $req)
    {
    	$this->validate($req,[
    		'Registrant_ID'=>'required',
    		]);
    	$id= $req->input("Registrant_ID");

        $reg=Registrant::where('ssn',$id)->first();


		$param=null;
		if($reg==null)
			$param=-1;
		else
        {
            $param=$reg->ssn;
        }

		return redirect()->action('AdminController@singleRegistrant', [$param]);
    }

    public function Registration(Request $req, $courseID){

        $this->validate($req ,[
            'ssn'=>'required|numeric',
            'name'=>'required|max:100|regex:/^[\pL\s\-]+$/u',
            'email'=>'required|email' ,
            'address'=>'required',
            'phone_number'=>'required|numeric'

        ]);

        $regist=Registrant::where('ssn',$req->ssn)->orwhere('email',$req->email)->first();
        $date = Carbon::now();
        //registration code to be able to download material
        $code = substr (md5(microtime()),0,7);
        $hashed_code=Hash::make($code);

        $course=Course::find($courseID);
        if($regist===null){

            $reg=new Registrant;
            $reg->ssn=$req->ssn;
            $reg->name=$req->name;
            $reg->email=$req->email;
            $reg->address=$req->address;
            $reg->phone_number=$req->phone_number;
            // $courseID->registrants()->save($reg);

            $reg->save();
            $course_reg =DB::table('course_registrant')->where('course_id',$courseID)->where('registrant_id',$reg->ssn)->first();
            $regID=$reg->ssn;
        }
        else {
            $course_reg =DB::table('course_registrant')->where('course_id',$courseID)->where('registrant_id',$regist->ssn)->first();
            $regID=$regist->ssn;
        }

        $msg="";

        if($course_reg==null){
            $course->registrants()->attach($req->ssn,['date_time'=>$date, 'code'=>$hashed_code]);
            $msg='Your Registation code is: '.$code.' The fees for the course must be paid within a week from this online registration at the training center or the registration will be cancelled.';
        }
        else $msg='you are already registered to this course.';

        \Session::flash('msg', $msg);
        return redirect()->back();

    }
    public function RegSuccess($regID,$courseID){

        echo "Registration is successful <br> Your Registation code is: {$regID} <br> The fees for the course must be paid within a week from this online registration at the training center or the registration fees  ";

    }

    public function confirm($course_id , $registrant_id)
    {
        $course=Course::find($course_id);
        $reg= Registrant::find($registrant_id);
        if ($course != null && $reg!=null)
        {
         DB::table('course_registrant')->where('course_id',$course->id)->where('registrant_id',$reg->ssn)->update(['confirmed' => '1']);
        }
        return redirect()->back();

    }

    public function addReview (Request $req, $courseID){
        $this->validate($req,[
            'code' => 'required',
            'review'=>'required'
        ]);

        $course = Course::find($courseID);

        if(!empty($course))
        {

            $registrations = DB::table('course_registrant')->where('course_id',$courseID)->get();

            foreach($registrations as $reg)
            {
                if (Hash::check($req->code,$reg->code))
                {
                    if (Hash::needsRehash($reg->code))
                    {
                        $hashed = Hash::make($req->code);
                        $reg->code=$hashed;
                        $reg->save();
                    }
                    if(!$reg->confirmed){
                        return redirect()->back()->with('errormsg',"your registration isn't confirmed yet to add a review");
                    }
                    $registrant=Registrant::find($reg->registrant_id);

                    $rev = new Review();
                    $rev->registrant_name= $registrant->name;
                    $rev->review=$req->review;
                    $rev->date_time=Carbon::now();
                    $rev->course_id=$courseID;

                    $rev->save();
                    Session::flash('msg', 'Review Added successfuly');
                    return redirect()->back();
                }

            }

            Session::flash('errormsg', "you aren't registered in this course");
            return redirect()->back()->withInput();

        }
        else
        {
            return redirect()->back();
        }


    }

    public function addRating (Request $req, $courseID){

//        echo $course->ratings->avg('value');


        $this->validate($req,[
            'code' => 'required',
            'rating'=>'required|numeric'
        ]);



        $course = Course::find($courseID);

        if(!empty($course))
        {

            $registrations = DB::table('course_registrant')->where('course_id',$courseID)->get();

            foreach($registrations as $reg)
            {
                if (Hash::check($req->code,$reg->code))
                {
                    if (Hash::needsRehash($reg->code))
                    {
                        $hashed = Hash::make($req->code);
                        $reg->code=$hashed;
                        $reg->save();
                    }
                    if(!$reg->confirmed){
                        return redirect()->back()->with('errormsg',"your registration isn't confirmed yet to rate");
                    }

                    //check if registrant already rated the course

                    #$registrant=Registrant::find($reg->registrant_id);
                    $user_ratings=  $course->ratings->where('registrant',$reg->registrant_id)->where('course_id',intval($courseID ))->count();

                    if($user_ratings !=0)
                        return redirect()->back()->with('errormsg',"your already rated this course");


                    $rate= new Rating();
                    $rate->value=$req->rating;
                    $rate->course_id=$courseID;
                    $rate->registrant=$registrant->ssn;
                    $rate->save();

                    Session::flash('msg', 'Rating Added successfuly');
                    return redirect()->back();
                }

            }

            Session::flash('errormsg', "you aren't registered in this course");
            return redirect()->back()->withInput();

        }
        else
        {
            return redirect()->back();
        }


    }
}
