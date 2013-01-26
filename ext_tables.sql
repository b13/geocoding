#
# Table structure for cache tables
#
CREATE TABLE tx_geocoding (
   id int(11) NOT NULL AUTO_INCREMENT,
   identifier varchar(128) NOT NULL DEFAULT '',
   crdate int(11) UNSIGNED NOT NULL DEFAULT '0',
   content mediumtext,
   lifetime int(11) UNSIGNED NOT NULL DEFAULT '0',
   PRIMARY KEY (id),
   KEY cache_id (`identifier`)
);
 
CREATE TABLE tx_geocoding_tags (
   id int(11) NOT NULL AUTO_INCREMENT,
   identifier varchar(128) NOT NULL DEFAULT '',
   tag varchar(128) NOT NULL DEFAULT '',
   PRIMARY KEY (id),
   KEY cache_id (`identifier`),
   KEY cache_tag (`tag`)
);