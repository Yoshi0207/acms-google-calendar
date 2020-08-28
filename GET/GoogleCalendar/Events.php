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

class Events extends ACMS_GET
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

        $firstDateOfYm = date('Y-m-d', strtotime('first day of ' . $ym));
        $lastDateOfYm = date('Y-m-d', strtotime('last day of ' . $ym));
        // 月末の終日イベントを表示するため
        $lastDateOfYm = date('Y-m-d', strtotime(date($lastDateOfYm).'+1 day'));

        $prevMonth = date('Y/m', strtotime(date($ymd) . '-1 month'));
        $nextMonth = date('Y/m', strtotime(date($ymd) . '+1 month'));
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
            'timeMin' => date('c',strtotime($firstDateOfYm)),
            'timeMax' => date('c',strtotime($lastDateOfYm))
        );
        $results = $service->events->listEvents($calendarId, $optParams);
        $events = $results->getItems();
        // イベント取得処理ここまで


        /**
         * データ成型処理
         */
        $eventsData = array();

        $week = ['日', '月', '火', '水', '木', '金', '土'];

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

                $weekNum = date('w', strtotime($eventDay));

                if ($time!="終日") {
                    $time = explode('+', explode('T', explode('-', $start)[2])[1])[0]."~".explode('+', explode('T', explode('-', $end)[2])[1])[0];
                }

                $url = 
                'http://www.google.com/calendar/event?'
                ."action="."TEMPLATE"
                ."&text=".$summary
                ."&details=".$description
                ."&location=".$location
                ."&dates=".date("Ymd\THis", strtotime($start))."/".date("Ymd\THis", strtotime($end));

                array_push($eventsData, array(
                    'day' => explode('T', explode('-', $start)[2])[0],
                    'week' => $week[$weekNum],
                    'time' => $time,
                    'summary' => $summary,
                    'url' => $url
                ));                
            }
        }
        // データ成型処理ここまで

        $obj = array(
            'date_set' => array(
                'prevMonth' => $prevMonth,
                'year' => $year,
                'month' => $month,
                'nextMonth' => $nextMonth,
            ),
            'events' => $eventsData,
            'count' => count($eventsData),
        );
        return $Tpl->render($obj);
    }

    private function getUTC() {

    }
}