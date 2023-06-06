<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        #Readme-by-Qazi.md -> Point 1
        if($request->has('user_id') && ($user_id = $request->get('user_id'))) {

            $response = $this->repository->getUsersJobs($user_id);

        }
        #Readme-by-Qazi.md -> Point 2
        elseif($request->__authenticatedUser->user_type == config('ADMIN_ROLE_ID') || $request->__authenticatedUser->user_type == config('SUPERADMIN_ROLE_ID'))
        {
            $response = $this->repository->getAll($request);
        }

        return response($response);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $job = $this->repository->with('translatorJobRel.user')->find($id);
        return response($job);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        #Readme-by-Qazi.md -> Point 3
        //validation are missing, and $request->all() is really bad practice, because user can spoof their own key/value inside post request

        $data = $request->all(); //instead of this, this should be like below

        //and another good practice can be separate the form request validation into FormRequest ->make:request FormRequest
        $data = $request->validate([
            'title' => 'required|unique:posts|max:255',
            'body' => 'required',
        ]);



        $response = $this->repository->store($request->__authenticatedUser, $data);

        return response($response);

    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        //validation is missing here and avoid request->all() explaint in readme point# 3
        $data = $request->all();
        $cuser = $request->__authenticatedUser;

        //define one method for re-utilization calling desire method -> callRepositoryFunction
        return self::callRepositoryFunction('updateJob', [
            $id,
            array_except($data, ['_token', 'submit']),
            $cuser
        ]);

    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        //validation is missing here and avoid request->all() explaint in readme point# 3
        $data = $request->all();
        return self::callRepositoryFunction('storeJobEmail', [$data]);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        #Readme-by-Qazi.md -> Point 4
        $userID = $request->__authenticatedUser->id;
        if ( $userID === $request->get('user_id')) {
            $response = self::callRepositoryFunction('getUsersJobsHistory', [$userID, $request]);
        }
        return isset($response) ? response($response) : null;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        //validation is missing here and avoid request->all() explaint in readme point# 3
        $data = $request->all();
        $user = $request->__authenticatedUser;
        return self::callRepositoryFunction('acceptJob', [$data, $user]);
    }

    public function acceptJobWithId(Request $request)
    {
        $jobID = $request->get('job_id');
        $user = $request->__authenticatedUser;
        return self::callRepositoryFunction('acceptJobWithId', [$jobID, $user]);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        //validation is missing here and avoid request->all() explaint in readme point# 3
        $data = $request->all();
        $user = $request->__authenticatedUser;
        return self::callRepositoryFunction('cancelJobAjax', [$data, $user]);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        //validation is missing here and avoid request->all() explaint in readme point# 3
        $data = $request->all();
        return self::callRepositoryFunction('endJob', [$data]);

    }

    public function customerNotCall(Request $request)
    {
        //validation is missing here and avoid request->all() explaint in readme point# 3
        $data = $request->all();
        return self::callRepositoryFunction('customerNotCall', [$data]);

    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        $user = $request->__authenticatedUser;
        return self::callRepositoryFunction('getPotentialJobs', [$user]);
    }

    public function distanceFeed(Request $request)
    {
        //validation is missing here and avoid request->all() explaint in readme point# 3
        $data = $request->all();

        $distance   = self::getDataValue($data, 'distance');
        $time       = self::getDataValue($data, 'time');
        $jobid      = self::getDataValue($data, 'jobid');
        $session    = self::getDataValue($data, 'session_time');
        $admComment = self::getDataValue($data, 'admincomment');

        $manuallyHandled = self::getDataStatus($data, 'manually_handled');
        $flagged    = self::getDataStatus($data, 'flagged');
        $byAdmin    = self::getDataStatus($data, 'by_admin');

        if ($flagged == 'yes' && $admComment == '') {
            return "Please, add comment";
        }
        
       
        if ($time || $distance) {

            Distance::where('job_id', '=', $jobid)->update(compact('distance', 'time'));
        }

        if ($admComment || $session || $flagged || $manuallyHandled || $byAdmin) {
            Job::where('id', '=', $jobid)->update([
                'admin_comments' => $admComment,
                'flagged' => $flagged,
                'session_time' => $session,
                'manually_handled' => $manuallyHandled,
                'by_admin' => $byAdmin
            ]);
        }

        return response('Record updated!');
    }

    public function reopen(Request $request)
    {
        //validation is missing here and avoid request->all() explaint in readme point# 3
        $data = $request->all();
        return self::callRepositoryFunction('reopen', [$data]);
    }

    public function resendNotifications(Request $request)
    {
        $job = $this->repository->find($request->get('jobid'));
        $jobData = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $jobData, '*');

        return response(['success' => 'Push sent']);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $job = $this->repository->find($request->get('jobid'));

        $this->repository->jobToData($job);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['success' => $e->getMessage()]);
        }
    }

    /**
     * Call respository function with their name
     */
    protected function callRepositoryFunction($functionName, $params) {
        return response($this->repository->$functionName($params));
    }

    /**
     * @param $data is any array
     * @param $key is the target key against which value will be checked
     */
    protected function getDataValue($data, $key) {
        return isset($data[$key]) && !is_null($data[$key]) ? $data[$key] : "";
    }

    /**
     * @param $data is any array
     * @param $key is the target key against which value will be yes or no
     */
    protected function getDataStatus($data, $key) {
        return isset($data[$key]) && $data[$key] == 'true' ? "yes" : "no";
    }

}
