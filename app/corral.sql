DROP TABLE settings;
DROP TABLE friends;
DROP TABLE availabilities;
DROP TABLE nudges;
DROP TABLE events;
DROP TABLE google_calendar;
DROP TABLE google_users;
DROP TABLE facebook_users;
DROP TABLE users;

CREATE TABLE settings (
  id int(11) NOT NULL AUTO_INCREMENT,
  source varchar(50) DEFAULT NULL,
  client_id varchar(255) DEFAULT NULL,
  client_secret varchar(255) NULL,
  created_at datetime NOT NULL,
  update_at datetime NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE users (
  id int(11) NOT NULL AUTO_INCREMENT,
  first_name varchar(100)  DEFAULT NULL,
  last_name varchar(100) DEFAULT NULL,
  email varchar(100) NOT NULL DEFAULT '',
  password varchar(255) DEFAULT NULL,
  remember_token varchar(255) DEFAULT NULL,
  valid tinyint(1) NOT NULL DEFAULT '0',
  mood varchar(50) DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE google_users (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  google_id varchar(255) DEFAULT NULL,
  google_access_token varchar(255) DEFAULT NULL,
  google_refresh_token varchar(255) DEFAULT NULL,
  google_id_token varchar(255) DEFAULT NULL,
  google_code varchar(255) DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE facebook_users (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  facebook_id varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  facebook_token varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY (id),
  KEY user_id (user_id),
  FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE friends (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  friend_id int(11) NOT NULL,
  friend_status tinyint(1) NOT NULL,
  sender_id int(11) DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE (user_id, friend_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE google_calendar (
  id varchar (255) NOT NULL,
  user_id int(11) DEFAULT NULL,
  sync_token varchar(255) DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE events (
  id varchar(50) NOT NULL,
  user_id int(11) DEFAULT NULL,
  calendar_id varchar (255) NOT NULL,
  start_date date DEFAULT NULL,
  start_time time DEFAULT NULL,
  end_date date DEFAULT NULL,
  end_time time DEFAULT NULL,
  summary varchar(255) DEFAULT NULL,
  created datetime DEFAULT NULL,
  updated datetime DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (calendar_id) REFERENCES google_calendar(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE nudges (
  id int(11) NOT NULL AUTO_INCREMENT,
  sender_id int(11) DEFAULT NULL,
  receiver_id int(11) DEFAULT NULL,
  message varchar(50) DEFAULT NULL,
  created_at datetime DEFAULT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE availabilities (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) DEFAULT NULL,
  start_time time DEFAULT NULL,
  end_time time DEFAULT NULL,
  start_date date DEFAULT NULL,
  end_date date DEFAULT NULL,
  status int(11) DEFAULT NULL,
  created_at datetime DEFAULT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;