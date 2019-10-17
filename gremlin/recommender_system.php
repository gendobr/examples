<?php
/**
 * Created by PhpStorm.
 * User: dobrovolskyi_h
 * Date: 21.08.2018
 * Time: 17:31
 */

namespace Wayfindr\Components\UserObjectViews;


abstract class UserObjectViews
{

    /** @var string */
    public $url;
    /** @var string */
    public $prefix;
    /** @var string */
    public $prefixLength;
    /** @var string */
    public $labelObject;
    /** @var array */
    public $labelUser = 'User';
    /** @var string */
    public $labelView = 'view';
    /** @var string */
    public $labelStatus = 'Status';
    /** @var string */
    public $labelDeleted = 'deleted';

    public function __construct(string $gremlinEndpointUrl, string $prefix)
    {
        $this->url = $gremlinEndpointUrl;
        $this->prefix = $prefix;
        $this->label = $prefix;
        $this->prefixLength = strlen($this->prefix);
    }

    /**
     * store fact "user $userId views object $objectId"
     * - repeated views are counted as one view
     * - if user vertex is not present it is created
     * - if object  vertex is not present it is created
     * @param $userId user identifier
     * @param $objectId object identifier
     */
    public abstract function addView(int $userId, int $objectId, int $timestamp);

    /**
     * count number the object $objectId is viewed
     * @param $objectId object identifier
     */
    public abstract function countViewsOf(int $objectId);


    /**
     * get list of $objectId viewed by $userId
     * @param $userId object identifier
     */
    public abstract function getViewedBy(int $userId);

    /**
     * test if $objectId was  viewed by $userId
     * @param $objectId object identifier
     * @param $userId user identifier
     */
    public abstract function isViewedByUser(int $userId, int $objectId);

    /**
     * connect the object $objectId to vertex 'object_status' with 'deleted' edge
     * @param $objectId object identifier
     */
    public abstract function hideObject(int $objectId);

    /**
     * remove the 'deleted' edge from the object $objectId to vertex 'object_status'
     * @param $objectId object identifier
     */
    public abstract function showObject(int $objectId);

    /**
     * - get all users that have viewed the object $objectId
     * - get all objects that were viewed by users found above
     * - ensure that all the objects are not connected to 'object_status' with 'deleted' edge
     * - exclude the object $objectId
     * - count nunber of views for each object
     * - place the objects by number of views in descending order
     * - get the desired range of records
     * @param $objectId object identifier
     * @param $start return object ids starting from $start position
     * @param $nRecords number of object ids to return
     * @return plain list of object identifiers ordered by number of views in descending order
     */
    public abstract function recommend(int $objectId, int $start = 0, int $nRecords = 10);

    /**
     * test if status of $objectId is "deleted"
     * @param $objectId object identifier
     */
    public abstract function isHidden(int $objectId);

    /**
     * count number the object $objectId is viewed
     * @param $objectId object identifier
     * @param $dates array of dates
     */
    public abstract function countViewsByDates(int $objectId, array $dates): int;

    /**
     * remove all from database
     */
    public abstract function clearAll();


    /**
     *  count views during current week
     */
    public function countViewsCurrentWeek($objectId, $timestamp)
    {
        $dayOfWeek = date('w', $timestamp);
        $daysBefore = $dayOfWeek;
        $daysAfter = 6 - $dayOfWeek;

        $oneDay = new \DateInterval('P1D');

        $dates = [];

        // current date
        $dates[] = date('Y-m-d', $timestamp);

        // dates before
        $date = new \DateTime(date('Y-m-d', $timestamp));
        for ($i = 1; $i <= $daysBefore; $i++) {
            $date->sub($oneDay);
            $dates[] = $date->format('Y-m-d');
        }

        // dates after
        $date = new \DateTime(date('Y-m-d', $timestamp));
        for ($i = 1; $i <= $daysAfter; $i++) {
            $date->add($oneDay);
            $dates[] = $date->format('Y-m-d');
        }
        return $this->countViewsByDates($objectId, $dates);
    }

    /**
     * @param array $dates
     * @param int $count
     * @return array
     */
    public function mostViewedByDates(array $dates, int $count)
    {
        $within = implode(', ', array_map(function ($date) {
            return "'{$date}'";
        }, $dates));

        $gremlinScript =
            " g.V().hasLabel('{$this->labelObject}')" // get list of Vertexes with label '{$this->labelObject}'
            .".not(out('{$this->labelDeleted}'))"
            .".order().by(inE('view').has('date', within({$within})).outV().dedup().count(), decr)"
            .".limit({$count})";

        $response = $this->runGremlinScript($gremlinScript);
        $result = [];
        $values = is_array($response['result']['data']['@value']) ? $response['result']['data']['@value'] : [];
        foreach ($values as $item) {
            $result[] = (int) str_replace($this->labelObject.'_', '', $item['@value']['id']);
        }
        return $result;
    }

    /**
     * Send script written in gremlin language
     * to REST endpoint of gremlin server
     * and decode the response that is supposed to be a JSON
     */
    public function runGremlinScript($gremlinScript)
    {
        if (strlen($this->url) == 0) {
            return null;
        }
        $client = new \GuzzleHttp\Client();
        try {
            $res = $client->request('POST', $this->url, ['json' => ['gremlin' => $gremlinScript]]);
            return json_decode($res->getBody(), $assoc = true);
        } catch (\Exception $e) {
             \Log::error($e->getMessage());
            return null;
        }
    }

    /**
     * add prefix to user id
     * @param $id user id
     * @return "user$id"
     */
    public function userUid(int $id): string
    {
        return "{$this->labelUser}_{$id}";
    }

    /**
     * add prefix to  object id
     * @param $id object id
     * @return $this->objectPrefix().$id
     */
    public function objectUid(int $id): string
    {
        return $this->prefix . $id;
    }

    /**
     * add prefix to  object id
     * @param $uid $this->objectPrefix().$id
     * @return $id
     */
    public function objectId(string $uid): int
    {
        return intval(substr($uid, $this->prefixLength));
    }

    /**
     * Exclude some objects from request.
     *
     * @param array $excludeIDs
     * @param null|string $selfID
     *
     * @return string
     */
    protected function getExcludeIDsStatement(array $excludeIDs = [], ?string $selfID = null): string
    {
        $without = [];

        foreach ($excludeIDs as $id) {
            $without[] = "'" . strval($this->objectUid($id)) . "'";
        }

        if (count($without) > 0) {
            $excludeIDsStatement = " has('uid', without(" . implode(',', $without) . ")).";
        } else {
            $excludeIDsStatement = '';
        }

        return $excludeIDsStatement;
    }
}





<?php
/**
 * Created by PhpStorm.
 * User: dobrovolskyi_h
 * Date: 21.08.2018
 * Time: 17:31
 */

namespace Wayfindr\Components\UserObjectViews;


class UserObjectViewsNeo4j extends UserObjectViews
{
    public function __construct(string $gremlinEndpointUrl, string $prefix)
    {
        $this->url = $gremlinEndpointUrl;
        $this->prefix = $prefix;
        $this->prefixLength = strlen($this->prefix);
        $this->labelObject = $prefix;
        $this->initGraph();
    }

    private function initGraph()
    {
        // g.V().coalesce( V().has('Status', 'uid', 'Status'),  addV().property('uid', 'Status'))
        $gremlinScript = "g.V().coalesce( ".
                                    "V().has('{$this->labelStatus}', 'uid', '{$this->labelStatus}'), ".
                                    "addV('{$this->labelStatus}').property('uid', '{$this->labelStatus}'))".
                              ".next()";
        $this->runGremlinScript($gremlinScript);
    }


    /**
     * send message to logger
     */
    public function log($message)
    {
        echo "\n";
        print_r($message);
        echo "\n";
    }

    /**
     * store fact "user $userId views job $jobId"
     * - repeated views are counted as one view
     * - if user vertex is not present it is created
     * - if job  vertex is not present it is created
     * @param int $userId user identifier
     * @param int $objectId
     * @param int $timestamp
     * @return
     */
    public function addView(int $userId, int $objectId, int $timestamp)
    {
        $userUid = $this->userUid($userId);
        $objectUid = $this->objectUid($objectId);
        $date = date('Y-m-d', $timestamp);

        $gremlinScript = "
            g.inject(1).
            coalesce( V().has('{$this->labelUser}', 'uid', '{$userUid}'), addV('{$this->labelUser}').property('uid', '{$userUid}')).as('user').
            coalesce( V().has('{$this->labelObject}' , 'uid', '{$objectUid}'), addV('{$this->labelObject}').property('uid', '{$objectUid}')).as('object').
            inE('{$this->labelView}').has('date', '{$date}').
            outV().has(id, '{$userUid}').
            fold().coalesce(unfold(), addE('{$this->labelView}').from(V().has('uid', '{$userUid}')).to(V().has('uid', '{$objectUid}')).property('date','{$date}')).
            iterate()
        ";

        $response = $this->runGremlinScript($gremlinScript);

        return $response['status'];
    }

    /**
     * test if $objectId was viewed by $userId
     * @param int $userId user identifier
     * @param int $objectId object identifier
     * @return bool
     */
    public function isViewedByUser(int $userId, int $objectId)
    {
        $userUid = $this->userUid($userId);
        $objectUid = $this->objectUid($objectId);
        $gremlinScript = "g.V().has('uid', '{$objectUid}').in('{$this->labelView}').has('uid', '{$userUid}').count()";
        $result = $this->runGremlinScript($gremlinScript);
        return isset($result['result']['data']['@value'][0]['@value']) && $result['result']['data']['@value'][0]['@value'] > 0;
    }

    /**
     * test if status of $objectId is "deleted"
     * @param int $objectId object identifier
     * @return bool
     */
    public function isHidden(int $objectId)
    {
        $objectUid = $this->objectUid($objectId);
        $gremlinScript = "g.V().has('{$this->labelObject}', 'uid', '{$objectUid}').out('{$this->labelDeleted}').count()";
        $result = $this->runGremlinScript($gremlinScript);
        return isset($result['result']['data']['@value'][0]['@value']) && $result['result']['data']['@value'][0]['@value'] > 0;
    }

    /**
     * count number the job $jobId is viewed
     * @param int $objectId
     * @return int
     */
    public function countViewsOf(int $objectId)
    {
        $objectUid = $this->objectUid($objectId);
        $gremlinScript = "g.V().hasLabel('{$this->labelObject}').has('uid','{$objectUid}').in('{$this->labelView}').dedup().count()";
        $result = $this->runGremlinScript($gremlinScript);
        return isset($result['result']['data']['@value'][0]['@value']) ? $result['result']['data']['@value'][0]['@value'] : 0;
    }

    /**
     * get list of $objectId viewed by $userId
     * @param int $userId object identifier
     * @return array
     */
    public function getViewedBy(int $userId)
    {
        $userUid = $this->userUid($userId);
        $gremlinScript = "g.V().has('uid','{$userUid}').out('{$this->labelView}').hasLabel('{$this->labelObject}').not(out('{$this->labelDeleted}')).dedup()";
        $response = $this->runGremlinScript($gremlinScript);
        $result = [];
        foreach ($response['result']['data']['@value'] as $item) {
            $result[] = intval($this->objectId($item['@value']['properties']['uid'][0]['@value']['value']));
        }
        return $result;
    }


    /**
     * count number the object $objectId is viewed
     * @param int $objectId object identifier
     * @param $dates array of dates
     * @return int
     */
    public function countViewsByDates(int $objectId, array $dates): int
    {
        $objectUid = $this->objectUid($objectId);

        if (!isset($this->tounion)) {
            $this->tounion = function ($d) {
                return "has('date','{$d}')";
            };
        }
        $subquery = join(',', array_map($this->tounion, $dates));
        $gremlinScript = "g.V().hasLabel('{$this->labelObject}').has('uid','{$objectUid}').inE().hasLabel('{$this->labelView}').union({$subquery}).outV().dedup().count()";
        $result = $this->runGremlinScript($gremlinScript);
        return isset($result['result']['data']['@value'][0]['@value']) ? $result['result']['data']['@value'][0]['@value'] : 0;
    }


    /**
     * connect the job $jobId to vertex 'status' with 'deleted' edge
     * @param $jobId job identifier
     */
    public function hideObject(int $objectId)
    {
        $objectUid = $this->objectUid($objectId);
        $gremlinScript = "
        	g.inject(1).
        	  coalesce( V().hasLabel('{$this->labelStatus}'), addV('{$this->labelStatus}') ).as('status').
        	  coalesce( V().has('{$this->labelObject}', 'uid', '{$objectUid}'), addV('{$this->labelObject}').property('uid', '{$objectUid}')).as('{$objectUid}').
        	  coalesce( V().has('{$this->labelObject}', 'uid', '{$objectUid}').out('{$this->labelDeleted}'),
        	            addE('{$this->labelDeleted}').from('{$objectUid}').to('status') ).
        	  iterate()
        ";
        $response = $this->runGremlinScript($gremlinScript);
        return isset($response['status']) ? $response['status'] : $response;
    }

    /**
     * remove all from database
     */
    public function clearAll()
    {
        if (config('app.env') == 'testing') {
            $gremlinScript = "g.V().hasLabel('{$this->labelObject}').drop().iterate()";
            $response = $this->runGremlinScript($gremlinScript);
            return isset($response['status']) ? $response['status'] : $response;
        }
    }

    /**
     * remove the 'deleted' edge from the job $jobId to vertex 'status'
     * @param $jobId job identifier
     */
    public function showObject(int $objectId)
    {
        $objectUid = $this->objectUid($objectId);
        $gremlinScript = " g.V().has('{$this->labelObject}', 'uid', '{$objectUid}').outE('{$this->labelDeleted}').drop().iterate()";
        $response = $this->runGremlinScript($gremlinScript);
        return $response['status'];
    }

    /**
     * - get all users that have viewed the job $jobId
     * - get all jobs that were viewed by users found above
     * - ensure that all the jobs are not connected to 'status' with 'deleted' edge
     * - exclude the job $jobId
     * - count nunber of views for each job
     * - place the jobs by number of views in descending order
     * - get the desired range of records
     * @param int $objectId
     * @param int $start return job ids starting from $start position
     * @param int $nRecords number of job ids to return
     * @param array $excludeIDs
     * @return array list of job identifiers ordered by number of views in descending order
     */
    public function recommend(int $objectId, int $start = 0, int $nRecords = 10, array $excludeIDs = [])
    {
        $excludeIDsStatement = $this->getExcludeIDsStatement($excludeIDs);

        $objectUid = $this->objectUid($objectId);
        $from = $start ? $start : 0;
        $to = $nRecords + $from;
        $gremlinScript =
            " g.V().hasLabel('{$this->labelObject}').   " // get list of Vertexes with label '{$this->labelObject}'
            . " has('uid','{$objectUid}').           " // then select object with uid= '{$objectUid}'
            . " in('{$this->labelView}').dedup().as('u').          " // then select all unique users that view the object
            . " out('{$this->labelView}').                         " // get outgoing objects
            . " hasLabel('{$this->labelObject}').         " // get outgoing objects
            . " not(out('{$this->labelDeleted}')).                 " // and objects are not deleted
            . " not(has('uid','{$objectUid}')).      " // and object is not current one
            . $excludeIDsStatement
            . " as('o').                             " // get outgoing objects
            . " dedup('u','o').                      "  // get unique pairs (user, object)
            . " select('o')                          "  // get objects
            . " groupCount().                      "  // group these objects
            . " by('uid').                         "  // by uid
            . " order(local).                      "  // order by count(*)
            . " by(values, Order.decr).            "  //
            . " unfold().                          "  // convert to list
            . " range({$from},{$to})               "  // get only part of the list
            . " ";
        $response = $this->runGremlinScript($gremlinScript);
        // return $response;
        $result = [];
        foreach ($response['result']['data']['@value'] as $item) {
            $result[] = intval($this->objectId($item['@value'][0]));
        }
        return $result;
    }
}






















<?php

namespace Wayfindr\Components\UserObjectViews;


class UserObjectViewsNeptune extends UserObjectViews
{

    public function __construct(string $gremlinEndpointUrl, string $prefix)
    {
        $this->url = $gremlinEndpointUrl;
        $this->prefix = $prefix;
        $this->prefixLength = strlen($this->prefix);
        $this->labelObject = $prefix;
        $this->initGraph();
    }

    private function initGraph()
    {
        $gremlinScript = "g.V('{$this->labelStatus}').count()";
        $response=$this->runGremlinScript($gremlinScript);
        if($response['result']['data']['@value'][0]['@value']==0){
            $gremlinScript = "g.addV('{$this->labelStatus}').property(id, '{$this->labelStatus}')";
            $this->runGremlinScript($gremlinScript);
        }
    }

    /**
     * store fact "user $userId views object $objectId"
     * - repeated views are counted as one view
     * - if user vertex is not present it is created
     * - if object  vertex is not present it is created
     * @param int $userId user identifier
     * @param int $objectId object identifier
     * @param int $timestamp
     * @return status of the operation
     */
    public function addView(int $userId, int $objectId, int $timestamp)
    {
        $userUid = $this->userUid($userId);
        $objectUid = $this->objectUid($objectId);
        $date = date('Y-m-d', $timestamp);

//        $gremlinScript = "g.V('{$this->labelStatus}')"
//            . ".coalesce( V('{$userUid}'),  addV('{$this->labelUser}').property(id, '{$userUid}') )"
//            . ".coalesce( V('{$objectUid}'),  addV('{$this->labelObject}').property(id, '{$objectUid}') )"
//            . ".addE('{$this->labelView}').from(V('{$userUid}')).to(V('{$objectUid}')).property('date','{$date}')";

        $gremlinScript = "g.V('{$this->labelStatus}')"
            . ".coalesce( V('{$userUid}'),  addV('{$this->labelUser}').property(id, '{$userUid}') )"
            . ".coalesce( V('{$objectUid}'),  addV('{$this->labelObject}').property(id, '{$objectUid}') )"
            .".inE('{$this->labelView}').has('date', '{$date}')"
            .".outV().has(id, '{$userUid}')"
            .".fold().coalesce(unfold(), addE('{$this->labelView}').from(V('{$userUid}')).to(V('{$objectUid}')).property('date','{$date}'))";

        $response = $this->runGremlinScript($gremlinScript);

        return $response['status'];
    }

    /**
     * count users that have viewed the object $objectId
     * @param int $objectId object identifier
     * @return int of object views
     */
    public function countViewsOf(int $objectId): int
    {
        $objectUid = $this->objectUid($objectId);
        $gremlinScript = "g.V('{$objectUid}').in('{$this->labelView}').dedup().count()";
        $result = $this->runGremlinScript($gremlinScript);
        return isset($result['result']['data']['@value'][0]['@value']) ? intval($result['result']['data']['@value'][0]['@value']) : 0;
    }

    /**
     * get list of $objectId viewed by $userId
     * @param int $userId object identifier
     * @return array
     */
    public function getViewedBy(int $userId){
        $userUid = $this->userUid($userId);
        $gremlinScript = "g.V('{$userUid}').out('{$this->labelView}').hasLabel('{$this->labelObject}').not(out('{$this->labelDeleted}')).dedup()";
        $response = $this->runGremlinScript($gremlinScript);
        $result = [];
        foreach ($response['result']['data']['@value'] as $item) {
            $result[] = intval($this->objectId($item['@value']['id']));
        }
        return $result;
    }

    /**
     * count number the object $objectId is viewed
     * @param int $objectId object identifier
     * @param $dates array of dates
     * @return int
     */
    public function countViewsByDates(int $objectId, array $dates): int
    {
        $objectUid = $this->objectUid($objectId);

        if (!isset($this->tounion)) {
            $this->tounion = function ($d) {
                return "has('date','{$d}')";
            };
        }
        $subquery = join(',', array_map($this->tounion, $dates));
        $gremlinScript = "g.V('{$objectUid}').inE().hasLabel('{$this->labelView}').union({$subquery}).outV().dedup().count()";
        $result = $this->runGremlinScript($gremlinScript);
        return isset($result['result']['data']['@value'][0]['@value']) ? $result['result']['data']['@value'][0]['@value'] : 0;
    }

    /**
     * test if $objectId was  viewed by $userId
     * @param int $userId user identifier
     * @param int $objectId object identifier
     * @return bool
     */
    public function isViewedByUser(int $userId,int $objectId){
        $userUid = $this->userUid($userId);
        $objectUid = $this->objectUid($objectId);
        $gremlinScript = "g.V('{$objectUid}').in('{$this->labelView}').hasId('{$userUid}').count()";
        $result = $this->runGremlinScript($gremlinScript);
        return isset($result['result']['data']['@value'][0]['@value']) && $result['result']['data']['@value'][0]['@value'] > 0;
    }

    /**
     * test if status of $objectId is "deleted"
     * @param int $objectId object identifier
     * @return bool
     */
    public function isHidden(int $objectId)
    {
        $objectUid = $this->objectUid($objectId);
        $gremlinScript = "g.V('{$objectUid}').out('{$this->labelDeleted}').hasId('{$this->labelStatus}').count()";
        $result = $this->runGremlinScript($gremlinScript);
        return isset($result['result']['data']['@value'][0]['@value']) && $result['result']['data']['@value'][0]['@value'] > 0;
    }

    /**
     * remove all from database
     */
    public function clearAll()
    {
        if (config('app.env') == 'testing') {
            $gremlinScript = "g.V().hasLabel('{$this->labelObject}').drop()";
            $response = $this->runGremlinScript($gremlinScript);
            return $response['status'];
        }
    }


    /**
     * connect the object $objectId to vertex 'object_status' with 'deleted' edge
     * @param int $objectId object identifier
     * @return status of the operation
     */
    public function hideObject(int $objectId)
    {
        $objectUid = $this->objectUid($objectId);
        $gremlinScript = "g.V('{$this->labelStatus}')"
            . ".coalesce("
            . "   V('{$objectUid}'), "
            . "   addV('{$this->labelObject}').property(id, '{$objectUid}') "
            . ")"
            . ".coalesce("
            . "   V('{$objectUid}').out('{$this->labelDeleted}').hasId('{$this->labelStatus}'),"
            . "   addE('{$this->labelDeleted}').from(V('{$objectUid}')).to(V('{$this->labelStatus}')).inV().hasId('{$this->labelStatus}')"
            . ")";
        $response = $this->runGremlinScript($gremlinScript);
        return $response['status'];
    }

    /**
     * remove the 'deleted' edge from the object $objectId to vertex 'object_status'
     * @param int $objectId object identifier
     * @return status of the operation
     */
    public function showObject(int $objectId)
    {
        $objectUid = $this->objectUid($objectId);
        $gremlinScript = "g.V().hasId('{$objectUid}').outE('{$this->labelDeleted}').drop()";
        $response = $this->runGremlinScript($gremlinScript);
        return $response['status'];
    }

    /**
     * - get all users that have viewed the object $objectId
     * - get all objects that were viewed by users found above
     * - ensure that all the objects are not connected to 'object_status' with 'deleted' edge
     * - exclude the object $objectId
     * - count nunber of views for each object
     * - place the objects by number of views in descending order
     * - get the desired range of records
     * @param int $objectId object identifier
     * @param int $start return object ids starting from $start position
     * @param int $nRecords number of object ids to return
     * @param array $excludeIDs
     * @return array list of object identifiers ordered by number of views in descending order
     */
    public function recommend(int $objectId, int $start = 0, int $nRecords = 15, array $excludeIDs = [])
    {
        $objectUid = $this->objectUid($objectId);
        $excludeIDsStatement = $this->getExcludeIDsStatement($excludeIDs, strval($objectUid));

        $from = $start ? $start : 0;
        $to = $nRecords + $from;

        $gremlinScript =
            " g.V('{$objectUid}').                 " // get the Vertex with id '{$objectUid}'
            . " in('{$this->labelView}').dedup().as('u').        " // then select all unique users that have viewed the object
            . " out('{$this->labelView}').                       " // get outgoing objects
            . " hasLabel('{$this->labelObject}').       " // get outgoing objects
            . " not(out('{$this->labelDeleted}')).               " // and objects are not deleted
            . " not(hasId({$excludeIDsStatement})).        " // and object is not current one
            . " as('o').                           " // get outgoing objects
            . " dedup('u','o').                    " // get unique pairs (user, object)
            . " select('o').                       " // get objects
            . " groupCount().                      " // group these objects
            . " by(id).                            " // by id
            . " order(local).                      " // order by count(*)
            . " by(values, Order.decr).            " //
            . " unfold().                          " // convert to list
            . " range({$from},{$to})               " // get only part of the list
            . " ";
        $response = $this->runGremlinScript($gremlinScript);
        $result = [];
        foreach ($response['result']['data']['@value'] as $item) {
            $result[] = intval($this->objectId($item['@value'][0]));
        }
        return $result;
    }

    /**
     * Exclude some objects from request.
     *
     * @param array $excludeIDs
     * @param null|string $selfID
     *
     * @return string
     */
    protected function getExcludeIDsStatement(array $excludeIDs = [], string $selfID = null): string
    {
        $without = ["'" . $selfID . "'"];

        foreach ($excludeIDs as $id) {
            $without[] = "'" . strval($this->objectUid($id)) . "'";
        }

        $excludeIDsStatement = implode(',', $without);

        return $excludeIDsStatement;
    }
}









<?php
/**
 * Created by PhpStorm.
 * User: dobrovolskyi_h
 * Date: 21.08.2018
 * Time: 17:31
 */

namespace Wayfindr\Components\UserObjectViews;


class UserObjectViewsTinker extends UserObjectViews
{
    public function __construct(string $gremlinEndpointUrl, string $prefix)
    {
        $this->url = $gremlinEndpointUrl;
        $this->prefix = $prefix;
        $this->prefixLength = strlen($this->prefix);
        $this->labelObject = $prefix;
    }

    /**
     * load data
     * for instance here you can switch to desired graph
     */
    public function loadDataScript()
    {
        //        return "
        //          // custom prefix, do not use it in Neptune
        //          g=TinkerGraph.open().traversal()
        //
        //          graph = g.getGraph()
        //          graph.io(IoCore.graphson()).readGraph('data/user-job-views.json')
        //      ";
    }

    /**
     * save data
     * here you can do some additional actions to save data
     * commit transaction etc
     */
    public function saveDataScript()
    {
        //        return "
        //      graph.io(IoCore.graphson()).writeGraph('data/user-job-views.json')
        //      ";
    }


    /**
     * store fact "user $userId views job $jobId"
     * - repeated views are counted as one view
     * - if user vertex is not present it is created
     * - if job  vertex is not present it is created
     * @param $userId user identifier
     * @param $jobId job identifier
     */
    public function addView(int $userId, int $objectId, int $timestamp)
    {
        $userUid = $this->userUid($userId);
        $objectUid = $this->objectUid($objectId);

        $date = date('Y-m-d', $timestamp);

        $gremlinScript = $this->loadDataScript() . "
            g.inject(1).
            coalesce( V().has('{$this->labelUser}', 'uid', '{$userUid}'), addV('{$this->labelUser}').property('uid', '{$userUid}')).as('user').
            coalesce( V().has('{$this->labelObject}' , 'uid', '{$objectUid}'), addV('{$this->labelObject}').property('uid', '{$objectUid}')).as('object').
            addE('{$this->labelView}').from('user').to('object').property('date','{$date}') .
            iterate()
        " . $this->saveDataScript();
        $response = $this->runGremlinScript($gremlinScript);
        if (!isset($response['status'])) {
            return null;
        }
        return $response['status'];

    }

    /**
     * test if $objectId was viewed by $userId
     * @param $objectId object identifier
     * @param $userId user identifier
     */
    public function isViewedByUser(int $userId, int $objectId)
    {
        $userUid = $this->userUid($userId);
        $objectUid = $this->objectUid($objectId);
        $gremlinScript = $this->loadDataScript() . "
            g.V().has('uid', '{$objectUid}').in('{$this->labelView}').has('uid', '{$userUid}').count()
        ";
        // print_r($gremlinScript);
        $result = $this->runGremlinScript($gremlinScript);
        // print_r($result);
        return isset($result['result']['data']['@value'][0]['@value']) && $result['result']['data']['@value'][0]['@value'] > 0;
    }

    /**
     * test if status of $objectId is "deleted"
     * @param $objectId object identifier
     */
    public function isHidden(int $objectId)
    {
        $objectUid = $this->objectUid($objectId);
        $gremlinScript = $this->loadDataScript() . "
            g.V().has('{$this->labelObject}', 'uid', '{$objectUid}').out('{$this->labelDeleted}').count()
        ";
        //print_r($gremlinScript);
        $result = $this->runGremlinScript($gremlinScript);
        //print_r($result);
        return isset($result['result']['data']['@value'][0]['@value']) && $result['result']['data']['@value'][0]['@value'] > 0;
    }

    /**
     * count number the job $jobId is viewed
     * @param $jobId job identifier
     */
    public function countViewsOf(int $objectId)
    {
        $objectUid = $this->objectUid($objectId);
        $gremlinScript = $this->loadDataScript() . "
            g.V().hasLabel('{$this->labelObject}').has('uid','{$objectUid}').in('{$this->labelView}').dedup().count()
        ";
        $result = $this->runGremlinScript($gremlinScript);
        return isset($result['result']['data']['@value'][0]['@value']) ? $result['result']['data']['@value'][0]['@value'] : 0;
    }

    /**
     * get list of $objectId viewed by $userId
     * @param $userId object identifier
     */
    public function getViewedBy(int $userId)
    {
        $userUid = $this->userUid($userId);
        $gremlinScript = $this->loadDataScript() . "
            g.V().has('uid','{$userUid}').out('{$this->labelView}').hasLabel('{$this->labelObject}').not(out('{$this->labelDeleted}')).dedup()
        ";
        $response = $this->runGremlinScript($gremlinScript);
        $result = [];
        foreach ($response['result']['data']['@value'] as $item) {
            $result[] = intval($this->objectId($item['@value']['properties']['uid'][0]['@value']['value']));
        }
        return $result;
    }


    /**
     * count number the object $objectId is viewed
     * @param $objectId object identifier
     * @param $dates array of dates
     */
    public function countViewsByDates(int $objectId, array $dates): int
    {
        $objectUid = $this->objectUid($objectId);


        if (!isset($this->tounion)) {
            $this->tounion = function ($d) {
                return "has('date','{$d}')";
            };
        }
        $subquery = join(',', array_map($this->tounion, $dates));
        $gremlinScript = $this->loadDataScript() . "
            g.V().hasLabel('{$this->labelObject}').has('uid','{$objectUid}').inE().hasLabel('{$this->labelView}').union({$subquery}).outV().dedup().count()
        ";
        $result = $this->runGremlinScript($gremlinScript);
        return isset($result['result']['data']['@value'][0]['@value']) ? $result['result']['data']['@value'][0]['@value'] : 0;
    }


    /**
     * connect the job $jobId to vertex 'status' with 'deleted' edge
     * @param $jobId job identifier
     */
    public function hideObject(int $objectId)
    {
        $objectUid = $this->objectUid($objectId);
        $gremlinScript = $this->loadDataScript() . "
            g.inject(1).
              coalesce( V().hasLabel('{$this->labelStatus}'), addV('{$this->labelStatus}') ).as('status').
              coalesce( V().has('{$this->labelObject}', 'uid', '{$objectUid}'), addV('{$this->labelObject}').property('uid', '{$objectUid}')).as('{$objectUid}').
              coalesce( V().has('{$this->labelObject}', 'uid', '{$objectUid}').out('{$this->labelDeleted}'),
                        addE('{$this->labelDeleted}').from('{$objectUid}').to('status') ).
              iterate()
        " . $this->saveDataScript();

        $response = $this->runGremlinScript($gremlinScript);
        // if (!isset($response['status'])) {
        //    print_r($response);
        //}
        return $response['status'];
    }

    /**
     * remove all from database
     */
    public function clearAll()
    {
        if (config('app.env') == 'testing') {
            $gremlinScript = $this->loadDataScript() . "
            g.V().hasLabel('{$this->labelObject}').drop().iterate()
            " . $this->saveDataScript();
            $response = $this->runGremlinScript($gremlinScript);
            return isset($response['status']) ? $response['status'] : $response;
        }
    }

    /**
     * remove the 'deleted' edge from the job $jobId to vertex 'status'
     * @param $jobId job identifier
     */
    public function showObject(int $objectId)
    {
        $objectUid = $this->objectUid($objectId);
        $gremlinScript = $this->loadDataScript() . "
            g.V().has('{$this->labelObject}', 'uid', '{$objectUid}').outE('{$this->labelDeleted}').drop().iterate()
        " . $this->saveDataScript();
        $response = $this->runGremlinScript($gremlinScript);
        return $response['status'];
    }

    /**
     * - get all users that have viewed the job $jobId
     * - get all jobs that were viewed by users found above
     * - ensure that all the jobs are not connected to 'status' with 'deleted' edge
     * - exclude the job $jobId
     * - count nunber of views for each job
     * - place the jobs by number of views in descending order
     * - get the desired range of records
     * @param int $objectId
     * @param int $start return job ids starting from $start position
     * @param int $nRecords number of job ids to return
     * @param array $excludeIDs
     * @return array list of job identifiers ordered by number of views in descending order
     */
    public function recommend(int $objectId, int $start = 0, int $nRecords = 10, array $excludeIDs = [])
    {
        $excludeIDsStatement = $this->getExcludeIDsStatement($excludeIDs);

        $objectUid = $this->objectUid($objectId);
        $from = $start ? $start : 0;
        $to = $nRecords + $from;

        $gremlinScript = $this->loadDataScript()
            . " g.V().hasLabel('{$this->labelObject}').   " // get list of Vertexes with label '{$this->labelObject}'
            . " has('uid','{$objectUid}').           " // then select object with uid= '{$objectUid}'
            . " in('{$this->labelView}').dedup().as('u').          " // then select all unique users that view the object
            . " out('{$this->labelView}').                         " // get outgoing objects
            . " hasLabel('{$this->labelObject}').         " // get outgoing objects
            . " not(out('{$this->labelDeleted}')).                 " // and objects are not deleted
            . " not(has('uid','{$objectUid}')).      " // and object is not current one
            . $excludeIDsStatement
            . " as('o').                             " // get outgoing objects
            . " dedup('u','o').                      "  // get unique pairs (user, object)
            . " select('o')                          "  // get objects
            . " groupCount().                      "  // group these objects
            . " by('uid').                         "  // by uid
            . " order(local).                      "  // order by count(*)
            . " by(values, Order.decr).            "  //
            . " unfold().                          "  // convert to list
            . " range({$from},{$to})               "  // get only part of the list
            . " ";
        $response = $this->runGremlinScript($gremlinScript);
        // return $response;
        $result = [];
        if(is_array($response['result']['data']['@value'])) {
            foreach ($response['result']['data']['@value'] as $item) {
                $result[] = intval($this->objectId($item['@value'][0]));
            }
        }

        return $result;
    }
}

