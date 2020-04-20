<?php

namespace App\Http\Controllers;

use App\CompletedTransaction;
use App\Parking;
use App\Payment;
use App\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public $successStatus = 200;

    public function lock(Request $req,$parking_id){
        if(!Parking::find($parking_id)){
            return $this->errorResponse("Invalid parking no");
        }
        $trans=Transaction::where('parking_id',$parking_id)->first();
        if(!$trans)
        {
            $tr=Transaction::create(['parking_id'=>$parking_id]);
            return response()->json(['success' => true], $this->successStatus);
        }
        else{
            return $this->errorResponse("Already locked");
        }
    }
    public function getTransaction(Request $req,$parking_id){
        $trans=Transaction::where('parking_id',$parking_id)->first();
        if($trans){
            $trans->unlock_requested_at=Carbon::now()->toDateTimeString();
            $trans->unlock_requested_by=Auth::user()->id;
            $this->calcFee($trans);
            $trans->save();
            

            $diff=$this->getHoursDiff($trans);
            return response()->json(
            [
                'success' => true,
                'details' => [
                    'parking_no'=>$parking_id,
                    'hours'=>(int)($diff),
                    'minutes'=>(int)(($diff-(int)$diff)*60),
                    'category'=>$trans->categories_applied,
                    'perHourRate'=>$trans->feeCategory->fee,
                    'amountDue'=>$trans->fee
                ],
                // 'trans'=>$trans 
            ]); 
        }
        else{
            if(!Parking::find($parking_id)){
                return $this->errorResponse("Invalid parking no");
            }
            else
                return $this->errorResponse("not locked");
        }
    }

    // public function getParkingId($parking_no){
    //     return substr($parking_no,8);
    // }


    function errorResponse($msg){
        return response()->json(
            [
                'success' => false,
                'message' => $msg 
            ]);
    }
    function succesResponse($msg){
        return response()->json(
            [
                'success' => true,
                'message' => $msg 
            ]);
    }

    function calcFee($trans){
        $fee_category=$trans->feeCategory;
        $trans->categories_applied=$fee_category->category_name;
        $trans->fee=($this->getHoursDiff($trans))*$fee_category->fee;
    }

    function getHoursDiff($trans){
        return 3.6;
        // return (new Carbon($trans->locked_at))->floatDiffInRealHours($trans->unlock_requested_at);
    }

    public function processPayment(Request $req,$parking_id){
        $trans=Transaction::where('parking_id',$parking_id)->first();
        $p=Payment::create(['amount_paid'=>$trans->fee,'paid_by'=>Auth::user()->id]);

        $trans->transaction_id=$p->id;

        CompletedTransaction::create($trans->toarray());

        if($this->unlock($trans)){
            $trans->delete();
            return $this->succesResponse("unlocked");
        }
        else{
            return $this->errorResponse("failed to unlock");
        }
    }

    function unlock($trans){
        $trans->unlocked_at=Carbon::now()->toTimeString();
        return true;
    }
    
}