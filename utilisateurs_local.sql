-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: ecoride
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `utilisateurs`
--

LOCK TABLES `utilisateurs` WRITE;
/*!40000 ALTER TABLE `utilisateurs` DISABLE KEYS */;
INSERT INTO `utilisateurs` VALUES (1,'lina','lina','','lina@example.com','$2y$10$Z2pZtS1mC2r2j3b6pV7yMe4m.qr5sPz1xgPp2oRr8tqzJm2Xv7bG6','utilisateur',40,'2025-08-20 17:24:37',NULL,1),(2,'alex','alex','','alex@example.com','$2y$10$Z2pZtS1mC2r2j3b6pV7yMe4m.qr5sPz1xgPp2oRr8tqzJm2Xv7bG6','utilisateur',60,'2025-08-20 17:24:37',NULL,1),(5,'cedricr06','Cédric','Rodrigues','cece06@gmail.com','$2y$10$D1n6Yfs9G7ZEkoia14V.XeluccxRWXPIujD/Y6OjAUPr14O/nq5.C','utilisateur',52,'2025-09-05 15:23:11','/uploads/avatars/5/e36dc90a62cedc9240581099.jpg',0),(7,'cece006','cece','roro','cece006@gmail.com','$argon2id$v=19$m=65536,t=4,p=1$RFNMNE9CYjNxczJISmlKdQ$EixzT0mGrDZsqQ5zMyf/Y75/30BIQnuvst84WsaxtM8','utilisateur',2,'2025-09-13 10:44:36','/uploads/avatars/7/0cda4d310c4951537c93e15a.jpg',0),(8,'cece01','cedric','durant','cece01@gmail.com','$argon2id$v=19$m=65536,t=4,p=1$RjVtZzRmVURyNzlJMEFuRQ$MP4gFEtRqwWryrOE2PrsxDHQ/i/0Q1UsHISX3IIeh7w','utilisateur',117,'2025-09-16 19:20:02',NULL,0),(9,'cece02','','','cece02@gmail.com','$argon2id$v=19$m=65536,t=4,p=1$WHBYUDJ0M1RydTUxQmF5Tw$aDlODmKty16uR0orG7APEHnG+iDOVM9Fu2L+jcjX/Ic','administrateur',0,'2025-09-21 21:56:52',NULL,0);
/*!40000 ALTER TABLE `utilisateurs` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-09-24 15:35:31
