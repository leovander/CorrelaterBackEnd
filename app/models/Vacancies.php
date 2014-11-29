<?php

class Vacancies extends Eloquent {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'vacancies';

    protected $connection = 'mysql2';

    protected $fillable = array('building', 'room_num', 'day', 'source', 'type', 'start', 'end');

}