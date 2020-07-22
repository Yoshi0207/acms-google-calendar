<?php

namespace Acms\Plugins\GoogleCalendar;

use DB;
use SQL;
use Field;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Common;

class Engine
{
    /**
     * @var \Field
     */
    protected $formField;

    /**
     * @var \Field
     */
    protected $config;

    /**
     * Engine constructor.
     * @param string $code
     */
    public function __construct($code, $module)
    {
        $field = $this->loadFrom($code);
        if (empty($field)) {
            throw new \RuntimeException('Not Found Form.');
        }
        $this->formField = $field;
        $this->module = $module;
        $this->code = $code;
        $this->config = $field->getChild('mail');
    }

    /**
     * Update Google Calendar
     */
    public function send()
    {
        $field = $this->module->Post->getChild('field');

        // GoogleCalendarAPIに渡す情報を生成
        $values = $this->makeCalendarValues($field);

        $this->update($values);
    }

    /**
     * Send Google Calendar Api
     *
     * @param array $values
     */
    protected function update($values)
    {
        $client = (new Api())->getClient();
        if (!$client->getAccessToken()) {
            throw new \RuntimeException('Failed to get the access token.');
        }
        $service = new Google_Service_Calendar($client);
        $calendarId = $this->config->get('calendar_id');

        $event = new Google_Service_Calendar_Event($values);

        $response = $service->events->insert($calendarId, $event);

        if (!$response->valid()) {
            throw new \RuntimeException('Failed to update the calendar.');
        }
    }


    /**
     * @param string $code
     * @return bool|Field
     */
    protected function loadFrom($code)
    {
        $DB = DB::singleton(dsn());
        $SQL = SQL::newSelect('form');
        $SQL->addWhereOpr('form_code', $code);
        $row = $DB->query($SQL->get(dsn()), 'row');

        if (!$row) {
            return false;
        }
        $Form = new Field();
        $Form->set('code', $row['form_code']);
        $Form->set('name', $row['form_name']);
        $Form->set('scope', $row['form_scope']);
        $Form->set('log', $row['form_log']);
        $Form->overload(unserialize($row['form_data']), true);

        return $Form;
    }

    protected function makeCalendarValues($field){

        // 設定項目について、チェックボックスの真偽値の配列
        // ablog cms カスタムフィールドを使用：true
        // ablog cms カスタムフィールドを使用しない：false
        // $checkItems:bool[]
        $checkItems = array(
            'calendar_start_date' => $this->config->get('calendar_start_date_check'),
            'calendar_start_time' => $this->config->get('calendar_start_time_check'),
            'calendar_end_date' => $this->config->get('calendar_end_date_check'),
            'calendar_end_time' => $this->config->get('calendar_end_time_check'),
            'calendar_event_timeZone' => $this->config->get('calendar_event_timeZone_check'),
        );

        // 各設定項目について、記述されている値の配列
        // $formField:string[]
        $formItems = array(
            'calendar_event_title' => $this->config->get('calendar_event_title'),
            'calendar_event_location' => $this->config->get('calendar_event_location'),
            'calendar_event_description' => $this->config->get('calendar_event_description'),
            'calendar_event_attendees' => $this->config->get('calendar_event_attendees'),
            'calendar_start_date' => $this->config->get('calendar_start_date'),
            'calendar_start_time' => $this->config->get('calendar_start_time'),
            'calendar_end_date' => $this->config->get('calendar_end_date'),
            'calendar_end_time' => $this->config->get('calendar_end_time'),
            'calendar_event_timeZone' => $this->config->get('calendar_event_timeZone'),
        );

        // GoogleCalendarAPIに渡される値
        // $values:string[]
        $values = array(
            // 予定タイトル
            'summary' => Common::getMailTxtFromTxt($formItems["calendar_event_title"], $field),

            // 予定場所
            'location' => Common::getMailTxtFromTxt($formItems["calendar_event_location"], $field),

            // 予定説明
            'description' => Common::getMailTxtFromTxt($formItems["calendar_event_description"], $field),

            // 開始時刻 yy-mm-ddT00:00:00timezone
            'start' => array(
                'dateTime' => $this->trueORfalse($checkItems["calendar_start_date"], $field->get($formItems["calendar_start_date"]), $formItems["calendar_start_date"])."T".$this->trueORfalse($checkItems["calendar_start_time"], $field->get($formItems["calendar_start_time"]), $formItems["calendar_start_time"]),// 開始日時
                'timeZone' => $formItems["calendar_event_timeZone"],
            ),

            // 終了時刻
            'end' => array(
                'dateTime' => $this->trueORfalse($checkItems["calendar_end_date"], $field->get($formItems["calendar_end_date"]), $formItems["calendar_end_date"])."T".$this->trueORfalse($checkItems["calendar_end_time"], $field->get($formItems["calendar_end_time"]), $formItems["calendar_end_time"]), // 終了日時
                'timeZone' => $formItems["calendar_event_timeZone"],
            ),

            // 参加者
            'attendees' => $this->makeAttendeesValue($formItems["calendar_event_attendees"], $field),

        );
        return $values;
    }

    // GoogleCalendarに送信する attendees の array を作成する
    // example: 'example@hotmail.co.jp, example@gmail.com, example@yahoo.co.jp'
    /*  becomes: array(
        array('email' => 'example@hotmail.co.jp),
        array('email' => 'example@gmail.com'),
        array('email' => 'example@yahoo.co.jp'),
    )*/
    // @return string[]
    private function makeAttendeesValue($attendeesTpl, $field){
        $str = Common::getMailTxtFromTxt($attendeesTpl, $field);

        // 空白文字(全角・半角)を削除
        $str = str_replace(array(" ", "　"), "", $str);

        $attendees = explode(',', $str);
        $array = array();
        foreach ($attendees as $attendee) {
            array_push($array,array('email' => $attendee));
        }
        return $array;
    }

    // 第一引数の値がtrueの時、第二引数を、falseの時第三引数を返す関数
    // $a:bool, $b,$c:any
    private function trueORfalse($a, $b, $c){
        if($a) {
            return $b;
        }
        return $c;
    }
}
