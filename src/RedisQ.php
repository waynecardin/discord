<?php

namespace RedisQ;

class RedisQ
{
    public function queueObject($object)
    {
        global $redis;

        $wrapped = serialize($object);

        $allQueues = new RedisTtlSortedSet('redisQ:allQueues');
	$objectQueues = new RedisTtlSortedSet('objectQueues');
        $queues = $allQueues->getMembers();

        $multi = $redis->multi();

        // Store an instance of the object
        $objectID = 'redisQ:objectID:'.uniqID().md5($wrapped);
	$objectQueues->add(time(), $objectID);
        $multi->setex($objectID, 9600, $wrapped);

        // Add objectID to all queues
        foreach ($queues as $queueID) {
            $multi->lPush($queueID, $objectID);
            $multi->expire($queueID, 9600);
        }
        $multi->exec();
    }

    public function registerListener($queueID)
    {
        $allQueues = new RedisTtlSortedSet('redisQ:allQueues');
        $allQueues->add(time(), $queueID);
    }

    public function listen($queueID, $timeToWait = 10)
    {
        global $redis;

        $timeToWait = max(1, min(10, $timeToWait));

        $rQueueID = "redisQ:queueID:$queueID";

        self::registerListener($rQueueID);

        do {
            $pop = $redis->blPop($rQueueID, $timeToWait);
            if (!isset($pop[1])) {
                return;
            }

            $objectID = $pop[1];
            $object = $redis->get($objectID);
        } while ($object === false);

        return unserialize($object);
    }
}
