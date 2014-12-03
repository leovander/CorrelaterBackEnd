<?php

class FacebookUsers extends Eloquent {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'facebook_users';

    protected $hidden = array('facebook_id', 'facebook_token');

    protected $primaryKey = 'user_id';

}