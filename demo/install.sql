-- phpMyAdmin SQL Dump
-- version 4.5.4.1deb2ubuntu2.1
-- http://www.phpmyadmin.net
--
-- Host: localhost:3306
-- Erstellungszeit: 31. Okt 2019 um 22:42
-- Server-Version: 5.7.27-0ubuntu0.16.04.1
-- PHP-Version: 7.0.33-0ubuntu0.16.04.7

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Datenbank: `test`
--
CREATE DATABASE IF NOT EXISTS `test` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `test`;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `galleries`
--

DROP TABLE IF EXISTS `galleries`;
CREATE TABLE IF NOT EXISTS `galleries` (
  `galleryId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `status` int(10) UNSIGNED DEFAULT '0',
  `rank` tinyint(3) UNSIGNED DEFAULT '0',
  `path` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `title` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci,
  `timestamp` decimal(12,0) NOT NULL,
  PRIMARY KEY (`galleryId`),
  KEY `status` (`status`),
  KEY `title` (`title`),
  KEY `path` (`path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Daten für Tabelle `galleries`
--

INSERT INTO `galleries` (`galleryId`, `status`, `rank`, `path`, `title`, `description`, `timestamp`) VALUES
(1, 0, 1, 'test', 'Test', 'Das ist ein Test.', '1402008611');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `gallery_images`
--

DROP TABLE IF EXISTS `gallery_images`;
CREATE TABLE IF NOT EXISTS `gallery_images` (
  `galleryImageId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `galleryId` int(10) UNSIGNED NOT NULL,
  `rank` tinyint(3) UNSIGNED NOT NULL,
  `filename` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `title` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8_unicode_ci,
  `timestamp` decimal(12,0) NOT NULL,
  PRIMARY KEY (`galleryImageId`),
  KEY `galleryId` (`galleryId`,`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Daten für Tabelle `gallery_images`
--

INSERT INTO `gallery_images` (`galleryImageId`, `galleryId`, `rank`, `filename`, `title`, `description`, `timestamp`) VALUES
(1, 1, 1, 'b1336919888899.jpg', '', NULL, '1402007918'),
(2, 1, 2, 'b1336968540820.png', '', NULL, '1402007958'),
(3, 1, 3, 'm_123494.jpg', 'nice', NULL, '1415389742');
