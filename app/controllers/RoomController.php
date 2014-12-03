<?php
date_default_timezone_set('America/Los_Angeles');
include(app_path()."/libraries/simple_html_dom.php");

class RoomController extends \BaseController
{
    function sortTimes (&$schedule) {
        // Obtain a list of columns to be sorted
        foreach ($schedule as $sched) {
            $building[] = $sched->building;
            $roomNum[] = $sched->room_num;
            $day[] = $sched->day;
            $startTime[] = $sched->start;
        }
        //sort by building >> room number >> day >> start time in order
        array_multisort($building, SORT_ASC, $roomNum, SORT_ASC, $day, SORT_ASC, $startTime, SORT_ASC, $schedule);
    }

    /**
     * @param $date - the date in format: yyyy-mm-dd
     * @param $time - the time in format: hh:mm:ss
     */
    public function getAvailabilities ($date, $time)
    {
        $now = date("H:i:s");

        //default to TODAY, if no argument given
        if ($date == "today") {
            $date = date("Y-m-d");
        } else {
            $date= date("Y-m-d", strtotime($date));
        }

        //default to NOW, if no argument given
        if ($time == "now") {
            $time = date("H:i:s");
        } else {
            $time = date("H:i:s", strtotime($time));
        }

        //find day of week based on date given
        $day_of_week = date('l', strtotime($date));

        $url = 'http://www.csulb.edu/depts/enrollment/registration/class_schedule/Fall_2014/By_Subject/';
        $html = file_get_html($url);
        foreach($html->find('p.update') as $data) {
            $update = ($data->plaintext);
        }
        $update = explode(":", $update);
        $updateDate = date("Y-m-d", strtotime($update[1]));


        $lastUpdate = DB::connection('mysql2')->table('settings')->get();
        if ($lastUpdate[0]->schedule_last_update == $updateDate && $lastUpdate[0]->url == $url) {
            //TODO
            //print("same, pass JSON file");
        } else {
            $this->getClassSchedule();
            $this->checkAndRemoveDup();
            $this->calcAllVacancies();
        }
        //print($day_of_week);
        $availabilities = DB::connection('mysql2')
                    ->table('vacancies')
                    ->where('day', '=', $day_of_week)
                    ->where('start', '<=', date("H:i:s", strtotime($time)))
                    ->where('end', '>', date("H:i:s", strtotime($time)))
                    ->get();

        $maxRemaining = 0;
        foreach ($availabilities as $key => $val) {
            $val->remaining  = round((strtotime(date("Y-m-d")." ".$val->end)
                    - strtotime(date("Y-m-d")." ".$now)) / 60);

            if ($val->remaining > $maxRemaining) {
                $maxRemaining = $val->remaining;
            }
            $val->maxRemaining = $maxRemaining;
        }

        return ($availabilities);
    }

    public function calcAllVacancies (){
        $week_days = array("Monday", "Tuesday", "Wednesday", "Thursday", "Friday");
        foreach($week_days as $week_day) {
            $this->calcVacancies($week_day);
        }
    }

    public function calcVacancies($day) {
        //$day = "Monday"; //todo debug - remove
        $startTimeOfDay = date('H:i:s', strtotime("8:00:00"));
        $endTimeOfDay = date("H:i:s", strtotime("23:00:00"));
        $vacancies = array();

        $eachDay = DB::connection('mysql2')->table('raw_data')->where('day', '=', $day)->get();
        $this->sortTimes($eachDay);
        Helpers::pr($eachDay);

        foreach ($eachDay as $key => $val) {
            if ($key + 1 <= sizeof($eachDay) - 1) {
                //if first element (key = 0) is not beginning of the day, there's available time from 8am
                if ($key == 0 && $val->start > date('H:i', strtotime("8:00:00"))) {
                    array_push($vacancies, array('building' => $val->building,
                        'room_num' => $val->room_num,
                        'day' => $val->day,
                        'source' => $val->source,
                        'type' => $val->type,
                        'start' => $startTimeOfDay,
                        'end' => $val->start));
                    //for all subsequent elements in array, need to check if there are the same rooms in a row.
                    //precondition: sorted per room, by time in ascending order
                } elseif ($eachDay[$key]->room_num != $eachDay[$key + 1]->room_num){
                    if ($eachDay[$key]->room_num != $eachDay[$key - 1]->room_num){
                        if ($val->start > $startTimeOfDay) { //no class starting at 8am, free from 8am
                            array_push($vacancies, array('building' => $val->building,
                                'room_num' => $val->room_num,
                                'day' => $val->day,
                                'source' => $val->source,
                                'type' => $val->type,
                                'start' => $startTimeOfDay,
                                'end' => $val->start));
                        } else { //otherwise, free from end of first class.
                            array_push($vacancies, array('building' => $val->building,
                                'room_num' => $val->room_num,
                                'day' => $val->day,
                                'source' => $val->source,
                                'type' => $val->type,
                                'start' => $val->end,
                                'end' => $eachDay[$key + 1]->start));
                        }
                        //if it's the only class for that day, add free time from end of that class to end of day.
                        array_push($vacancies, array('building' => $val->building,
                            'room_num' => $val->room_num,
                            'day' => $val->day,
                            'source' => $val->source,
                            'type' => $val->type,
                            'start' => $val->end,
                            'end' => $endTimeOfDay));
                    } elseif ($eachDay[$key]->room_num == $eachDay[$key - 1]->room_num) {
                        array_push($vacancies, array('building' => $val->building,
                            'room_num' => $val->room_num,
                            'day' => $val->day,
                            'source' => $val->source,
                            'type' => $val->type,
                            'start' => $val->end,
                            'end' => $endTimeOfDay));
                    }

                } elseif ($eachDay[$key]->room_num == $eachDay[$key + 1]->room_num) {
                    if($eachDay[$key]->room_num != $eachDay[$key - 1]->room_num){
                        if($val->start > $startTimeOfDay) {
                            array_push($vacancies, array('building' => $val->building,
                                'room_num' => $val->room_num,
                                'day' => $val->day,
                                'source' => $val->source,
                                'type' => $val->type,
                                'start' => $startTimeOfDay,
                                'end' => $val->start));
                            array_push($vacancies, array('building' => $val->building,
                                'room_num' => $val->room_num,
                                'day' => $val->day,
                                'source' => $val->source,
                                'type' => $val->type,
                                'start' => $val->end,
                                'end' => $eachDay[$key + 1]->start));
                        } else { //if start at 8am, assuming that's the earliest class
                            array_push($vacancies, array('building' => $val->building,
                                'room_num' => $val->room_num,
                                'day' => $val->day,
                                'source' => $val->source,
                                'type' => $val->type,
                                'start' => $val->end,
                                'end' => $eachDay[$key + 1]->start));
                        }
                    } elseif ($eachDay[$key]->room_num == $eachDay[$key - 1]->room_num) {
                        array_push($vacancies, array('building' => $val->building,
                            'room_num' => $val->room_num,
                            'day' => $val->day,
                            'source' => $val->source,
                            'type' => $val->type,
                            'start' => $val->end,
                            'end' => $eachDay[$key + 1]->start));
                    }
                } else {
                    array_push($vacancies, array('building' => $val->building,
                        'room_num' => $val->room_num,
                        'day' => $val->day,
                        'source' => $val->source,
                        'type' => $val->type,
                        'start' => $val->end,
                        'end' => $endTimeOfDay));
                }
            }
        } //end for-loop

        //last item in the array
        $s = sizeof($eachDay) - 1; //index of last element
        if ($eachDay[$s]->room_num != $eachDay[$s - 1]->room_num){
            if ($val->start > $startTimeOfDay) { //no class starting at 8am, free from 8am
                array_push($vacancies, array('building' => $val->building,
                    'room_num' => $val->room_num,
                    'day' => $val->day,
                    'source' => $val->source,
                    'type' => $val->type,
                    'start' => $startTimeOfDay,
                    'end' => $val->start));
            }
            //if it's the only class for that day, add free time from end of that class to end of day.
            array_push($vacancies, array('building' => $val->building,
                'room_num' => $val->room_num,
                'day' => $val->day,
                'source' => $val->source,
                'type' => $val->type,
                'start' => $val->end,
                'end' => $endTimeOfDay));
        } elseif ($eachDay[$s]->room_num == $eachDay[$s - 1]->room_num) {
            array_push($vacancies, array('building' => $val->building,
                'room_num' => $val->room_num,
                'day' => $val->day,
                'source' => $val->source,
                'type' => $val->type,
                'start' => $val->end,
                'end' => $endTimeOfDay));
        }

        Helpers::pr($vacancies);
        //save the vacancies into DB
        foreach ($vacancies as $vancancy) {
            $data = new Vacancies();
            $data->building = $vancancy['building'];
            $data->room_num = $vancancy['room_num'];
            $data->day = $vancancy['day'];
            $data->source = $vancancy['source'];
            $data->type = $vancancy['type'];
            $data->start = $vancancy['start'];
            $data->end = $vancancy['end'];
            $data->save();
        }
    }

    public function getClassSchedule() {
        $majors = array("CHzE", "CzE", "CECS", "CEM", "EzE", "ENGR", "EzT", "MAE");
        //$majors = array("EzE", "ENGR", "MAE"); //todo: debug - remove
        foreach ($majors as $major) {
            $this->getClassScheduleHelper($major);
        }
    }

    public function checkAndRemoveDup() {
        $schedule = DB::connection('mysql2')->table('raw_data')->get();
        $this->sortTimes($schedule);
        foreach ($schedule as $key => $val) {
            if ($key + 1 <= sizeof($schedule) - 1) {
                if ($schedule[$key]->building == $schedule[$key + 1]->building
                    && $schedule[$key]->room_num == $schedule[$key + 1]->room_num) {
                    if ($schedule[$key]->day == $schedule[$key + 1]->day
                        && $schedule[$key]->start == $schedule[$key + 1]->start
                        && $schedule[$key]->end == $schedule[$key + 1]->end) {

                        //get all the duplicate ones
                        $toDelete = DB::connection('mysql2')->table('raw_data')
                            ->where('raw_data.building', '=', $val->building)
                            ->where('raw_data.room_num', '=', $val->room_num)
                            ->where('raw_data.day', '=', $val->day)
                            ->where('raw_data.start', '=', $val->start)
                            ->where('raw_data.end', '=', $val->end)
                            ->get();

                        //delete duplicate except last one
                        foreach ($toDelete as $k => $v) {
                            if (!($k == sizeof($toDelete) - 1)) {
                                $delete = RawData::find($v->id);
                                $delete->delete();
                            }
                        }
                    }
                }
            }
        }
    }

    public function getClassScheduleHelper($major) {
        $html = file_get_html('http://www.csulb.edu/depts/enrollment/registration/class_schedule/Fall_2014/By_College/'.$major.'.html');
        $ret = $html->find('.sectionTable');

        $schedule = array();

        $today = getdate();

        foreach($ret AS $table) {
            $rows = $table->find('tr');
            $count = 0;

            foreach($rows AS $row) {
                if($count != 0) {
                    $room = $row->children(7)->innertext;
                    $time = $row->children(5)->innertext;
                    $days = $row->children(4)->innertext;
                    $type = $row->children(3)->innertext;

                    $time = explode("-", $time);
                    $room = explode("-", $room);

                    if (isset($time[1]) && isset($time[0])) {
                        //Logic to append 'AM' or 'PM' to the start and end time
                        if (substr($time[1], -2) == 'AM') {
                            $time[0] = $time[0].'AM';
                        } else {
                            if (preg_match('/12?/', $time[1]) && preg_match('/1(0|1)/', $time[0])) {
                                $time[0] = $time[0].'AM';
                            } else {
                                $time[0] = $time[0].'PM';
                            }
                        }

                        //Convert start time and end time to time object
                        $time[0] = date('H:i', strtotime($time[0]));
                        $time[1] = date('H:i', strtotime($time[1]));
                    }

                    preg_match_all('/(M|Tu|W|Th|F)/', $days, $matches);
                    foreach($matches[0] AS $match) {
                        switch($match) {
                            case 'M':
                                array_push($schedule, array('building'=> $room[0],
                                    'roomNum' => $room[1],
                                    'day' => 'Monday',
                                    'source' => $major,
                                    'type' => $type,
                                    'start' => $time[0],
                                    'end' => $time[1]));
                                break;
                            case 'Tu':
                                array_push($schedule, array('building'=> $room[0],
                                    'roomNum' => $room[1],
                                    'day' => 'Tuesday',
                                    'source' => $major,
                                    'type' => $type,
                                    'start' => $time[0],
                                    'end' => $time[1]));
                                break;
                            case 'W':
                                array_push($schedule, array('building'=> $room[0],
                                    'roomNum' => $room[1],
                                    'day' => 'Wednesday',
                                    'source' => $major,
                                    'type' => $type,
                                    'start' => $time[0],
                                    'end' => $time[1]));
                                break;
                            case 'Th':
                                array_push($schedule, array('building'=> $room[0],
                                    'roomNum' => $room[1],
                                    'day' => 'Thursday',
                                    'source' => $major,
                                    'type' => $type,
                                    'start' => $time[0],
                                    'end' => $time[1]));
                                break;
                            default:
                                array_push($schedule, array('building'=> $room[0],
                                    'roomNum' => $room[1],
                                    'day' => 'Friday',
                                    'source' => $major,
                                    'type' => $type,
                                    'start' => $time[0],
                                    'end' => $time[1]));
                                break;
                        }
                    }
                }
                $count++;
            }
        }
        $this->saveToDb($schedule); //save raw data
        return json_encode($schedule);
    }

    public function saveToDb(&$schedule) {  //save raw data
        foreach ($schedule as $sched) {
            $data = new RawData();
            $data->building = $sched['building'];
            $data->room_num = $sched['roomNum'];
            $data->day = $sched['day'];
            $data->source = $sched['source'];
            $data->type = $sched['type'];
            $data->start = $sched['start'];
            $data->end = $sched['end'];
            $data->save();
        }
    }
}