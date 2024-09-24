CREATE TABLE `blogs` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `title` varchar(255) NOT NULL,
 `content` text NOT NULL,
 `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8
CREATE TABLE `blogs_contacts` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `name` varchar(100) NOT NULL,
 `email` varchar(100) NOT NULL,
 `message` text NOT NULL,
 `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8
CREATE TABLE `blogs_tags` (
 `blogs_id` int(11) NOT NULL,
 `tag_id` int(11) NOT NULL,
 PRIMARY KEY (`blogs_id`,`tag_id`),
 KEY `blogs_tags_ibfk_2` (`tag_id`),
 CONSTRAINT `blogs_tags_ibfk_1` FOREIGN KEY (`blogs_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE,
 CONSTRAINT `blogs_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8
CREATE TABLE `tags` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `name` varchar(255) NOT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=507 DEFAULT CHARSET=utf8
CREATE TABLE `users` (
 `id` int(10) NOT NULL AUTO_INCREMENT,
 `username` varchar(100) NOT NULL,
 `password` varchar(60) NOT NULL,
 `email` varchar(80) NOT NULL,
 `isadmin` tinyint(1) NOT NULL,
 `avatar` varchar(100) NOT NULL,
 `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8