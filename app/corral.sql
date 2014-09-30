DROP TABLE settings;
DROP TABLE group_members;
DROP TABLE groups;
DROP TABLE friends;
DROP TABLE events;
DROP TABLE users;

CREATE TABLE settings (
  id int(11) NOT NULL AUTO_INCREMENT,
  source varchar(50) DEFAULT NULL,
  client_id varchar(255) DEFAULT NULL,
  client_secret varchar(255) NULL,
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
  google_id varchar(255) DEFAULT NULL,
  google_access_token varchar(255) DEFAULT NULL,
  google_refresh_token varchar(255) DEFAULT NULL,
  google_id_token varchar(255) DEFAULT NULL,
  google_code varchar(255) DEFAULT NULL,
  status tinyint(1) DEFAULT '1',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE groups (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  name varchar(100) NOT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE friends (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  friend_id int(11) NOT NULL,
  friend_status tinyint(1) NOT NULL,
  sender_id int(11) DEFAULT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE (user_id, friend_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE group_members (
  id int(11) NOT NULL AUTO_INCREMENT,
  group_id int(11) NOT NULL,
  user_id int(11) NOT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE events (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) DEFAULT NULL,
  start_time datetime DEFAULT NULL,
  end_time datetime DEFAULT NULL,
  kind varchar(50) DEFAULT NULL,
  created datetime DEFAULT NULL,
  updated datetime DEFAULT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
