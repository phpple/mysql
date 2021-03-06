set names utf8;
use phpple;
drop table if exists u_user;
create table u_user (
    `id` bigint unsigned not null auto_increment,
    `username` varchar(20) not null,
    `password` char(40) not null,
	`email`    char(64) not null default '',
	`phone`    bigint unsigned not null default '0',
    `view_num` int unsigned not null default 0,
    `city_id` mediumint unsigned not null default '0',
   	`status` tinyint unsigned not null default '0',
	`del_flag` tinyint unsigned not null default '0',
	`create_time` timestamp not null default current_timestamp,
	`update_time` timestamp not null default current_timestamp,
    primary key(`id`)
) engine innodb charset utf8;

insert into u_user(`id`, `username`, `password`, `status`) values(10000, 'ronnie', '1b927c6a9d291f12a60139ae0a65cf51461cace3', 1);
insert into u_user(`id`, `username`, `password`, `status`) values(10001, 'ronnie', '1b927c6a9d291f12a60139ae0a65cf51461cace3', 1);
