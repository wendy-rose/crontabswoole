/*
Navicat MySQL Data Transfer

Source Server         : docker-mysql
Source Server Version : 50638
Source Host           : 127.0.0.1:3306
Source Database       : crontab

Target Server Type    : MYSQL
Target Server Version : 50638
File Encoding         : 65001

Date: 2019-07-04 10:07:12
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for cron
-- ----------------------------
DROP TABLE IF EXISTS `cron`;
CREATE TABLE `cron` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `taskname` char(50) NOT NULL DEFAULT '' COMMENT '任务名称',
  `rule` char(50) NOT NULL DEFAULT '' COMMENT 'crontab规则，秒，分，时，日，月，周',
  `timeout` int(10) unsigned NOT NULL DEFAULT '30' COMMENT '脚本运行时间',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0正常，1暂停',
  `execute` varchar(255) NOT NULL DEFAULT '' COMMENT '运行命令',
  `runtime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '这次运行的时间',
  `num` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '已经运行的次数',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for process
-- ----------------------------
DROP TABLE IF EXISTS `process`;
CREATE TABLE `process` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `taskid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '任务id',
  `runid` char(32) NOT NULL DEFAULT '' COMMENT '自定义运行进程id',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0开始运行，1正常结束，2程序运行出错，3运行超时',
  `start` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '开始时间戳',
  `end` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '结束时间戳',
  `signal` int(4) unsigned NOT NULL DEFAULT '0' COMMENT '信号',
  `pipe` int(8) unsigned NOT NULL DEFAULT '0' COMMENT '管道',
  `pid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '执行进程id',
  `code` int(10) unsigned NOT NULL DEFAULT '1' COMMENT '管道返回码',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
