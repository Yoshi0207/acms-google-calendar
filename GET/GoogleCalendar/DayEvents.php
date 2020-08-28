<?php

namespace Acms\Plugins\GoogleCalendar\GET\GoogleCalendar;

use ACMS_GET;
use Template;
use DB;
use SQL;
use ACMS_Filter;
use Config;
use ACMS_Corrector;
use Google_Service_Calendar;
use Acms\Plugins\GoogleCalendar\Api;

/**
 * テンプレート上で、標準のGETモジュールと同様に、
 * '<!-- BEGIN_MODULE GoogleCalendar_Events --><!--END_MODULE GoogleCalendar_Events -->'
 * で呼び出す。
 */

class DayEvents extends ACMS_GET
{
    public function get()
    {        
        $Tpl = new Template($this->tpl, new ACMS_Corrector());

        /**
         * 日付設定
         */
        $start_date = requestTime();

        $ymd = substr(START, 0, 10);
        if('1000-01-01' === $ymd ) {
            $ymd = date('Y-m-d', requestTime());
        }
        $start_date = $ymd.' 00:00:00';

        list($year, $month, $day) = explode('-', $start_date);
        $ym = substr($ymd, 0, 7);

        $prevDay = date('Y/m/d', strtotime(date($ymd) . '-1 day'));
        $nextDay = date('Y/m/d', strtotime(date($ymd) . '+1 day'));
        // 日付設定ここまで


        /**
         * GoogleCalendar設定
         */
        $client = (new Api())->getClient();

        // サービスオブジェクトの用意
        $service = new Google_Service_Calendar($client);

        $this->config = Config::loadDefaultField();
        $this->config->overload(Config::loadBlogConfig(BID));

        // カレンダーID
        $calendarId = config('calendarID');
        // GoogleCalendar設定ここまで


        /**
         * イベント取得処理
         */
        $optParams = array(
            //'maxResults' => 10,
            'orderBy' => 'startTime',
            'singleEvents' => true,
            'timeMin' => date('c',strtotime($ymd)),
            'timeMax' => date('c',strtotime($nextDay))
        );
        $results = $service->events->listEvents($calendarId, $optParams);
        $events = $results->getItems();
        // イベント取得処理ここまで


        /**
         * データ成型処理
         */
        $eventsData = array();

        if (empty($events)) {
            // throw new \RuntimeException('No upcoming events found.');
        } else {
            foreach ($events as $event) {
                $time = "";
                $start = $event->start->dateTime;
                if (empty($start)) {
                    $start = $event->start->date;
                    $time = "終日";
                }
                $end = $event->end->dateTime;
                if (empty($end)) {
                    $end = $event->end->date;
                }
                $summary = $event->summary;
                $description = $event->description;
                $location = $event->location;

                $eventDay = explode('T', $start)[0];

                if ($time!="終日") {
                    $time = explode('+', explode('T', explode('-', $start)[2])[1])[0];
                }

                $url = 
                'http://www.google.com/calendar/event?'
                ."action="."TEMPLATE"
                ."&text=".$summary
                ."&details=".$description
                ."&location=".$location
                ."&dates=".date("Ymd\THis", strtotime($start))."/".date("Ymd\THis", strtotime($end));

                array_push($eventsData, array(
                    'time' => $time."~",
                    'summary' => $summary,
                    'url' => $url
                ));                
            }
        }
        // データ成型処理ここまで

        $obj = array(
            'date_set' => array(
                'prevDay' => $prevDay,
                'year' => $year,
                'month' => $month,
                'day' => explode(' ', $day)[0],
                'nextDay' => $nextDay,
            ),
            'events' => $eventsData,
            'count' => count($eventsData),
        );
        if (empty($events)) {
            $obj["notFound"] = (object)[];
        }

        return $Tpl->render($obj);
    }
}