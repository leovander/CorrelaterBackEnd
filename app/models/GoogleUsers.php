<?php

class GoogleUsers extends Eloquent {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'google_users';

    protected $hidden = array('google_code', 'google_id', 'google_id_token', 'google_refresh_token');

    protected $primaryKey = 'user_id';

}