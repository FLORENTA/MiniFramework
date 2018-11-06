<?php

namespace Lib\Routing;

use Lib\Event\Events\StatisticEvent;
use Lib\Model\Orm\EntityManager;
use Entity\Game;
use Entity\Dummy as StatisticEntity;
use Entity\Dummy;
use Entity\User;

/**
 * Class StatisticEventListener
 * @package Lib\Routing
 */
class StatisticEventListener
{
    /**
     * @var array $urlsToNotSave
     */
    protected $urlsToNotSave = [];

    /**
     * @var EntityManager $em
     */
    protected $em;

    /**
     * Statistic constructor.
     * @param EntityManager $manager
     */
    public function __construct(EntityManager $manager)
    {
        $this->em = $manager;
    }

    public function setArrayOfRoutesToNotSave()
    {
        $this->urlsToNotSave[] = '/favicon.ico';
        foreach ($GLOBALS['routeCollection'] as $id => $route) {
            if (preg_match("#^/admin#", $route['url'])) {
                $this->urlsToNotSave[] = $route['url'];
            }
        }
    }

    /**
     * @param StatisticEvent $event
     * @throws \Exception
     */
    public function save(StatisticEvent $event)
    {
        $this->setArrayOfRoutesToNotSave();

        /* Prevent from saving admin urls */
        if (!in_array($event->getUri(), $this->urlsToNotSave)) {
            $statistic = (new StatisticEntity)->setRoute($event->getUri());
            $this->em->persist($statistic);
        }
    }
}