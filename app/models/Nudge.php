<?php

class Nudge extends Eloquent {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'nudges';

    protected $fillable = array('sender_id', 'receiver_id', 'message');
}