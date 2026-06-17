-- MySQL dump 10.13  Distrib 8.0.45, for macos15 (x86_64)
--
-- Host: localhost    Database: SISGI_db
-- ------------------------------------------------------
-- Server version	5.5.5-10.11.13-MariaDB-0ubuntu0.24.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `inventario_soporte`
--

DROP TABLE IF EXISTS `inventario_soporte`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_soporte` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `num_inventario` varchar(50) NOT NULL,
  `tipo` enum('mouse','teclado','laptop','all in one','cpu','monitor','no-break','iPad','camara','impresora','escaner','ups') NOT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `num_serie` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `personal_asignado` varchar(150) DEFAULT NULL,
  `ubicacion` enum('soporte palacio centro','soporte palacio nuevo','Sistemas','Redes','Administración','Dirección','Pagina Web') NOT NULL,
  PRIMARY KEY (`num_inventario`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventario_soporte`
--

LOCK TABLES `inventario_soporte` WRITE;
/*!40000 ALTER TABLE `inventario_soporte` DISABLE KEYS */;
INSERT INTO `inventario_soporte` VALUES (4,'294104192','mouse','Lenovo','LXH-EMS-10ZA','410A4910','Mouse color blanco Lenovo','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(21,'294104498','impresora','HP','Laserjet P1102w','VND3X05219','Color negro ubicado en las oficinas de soporte técnico (anaquel de aluminio)','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(34,'294105093','mouse','Dell','No visible','CN-009NK2-73826-66R-0TWD','color negro ubicado en soporte técnico','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(9,'294105161','mouse','DELL','MS116p','S/N','Mouse negro DELL','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(8,'294105162','mouse','DELL','MS116p','S/N','Mouse color negro DELL','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(10,'294105164','teclado','DELL','KB216t','S/N','Teclado negro DELL','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(28,'294105254','teclado','Dell','KB216t3','CN019M93LO30031AG1LCA03','teclado color negro ubicado en soporte técnico','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(5,'294105824','mouse','DELL','MOC5UO','S/N','Mouse color negro DELL','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(15,'294106404','no-break','Complet','MT505','21ZY050310','No-Break color negro Complet','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(19,'294106406','no-break','Complet','MT805','21ZY050294','Color negro con 8 puertos AC (se encuentra en el área de soporte técnico)','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(29,'294106409','no-break','Complet','MT505','21ZY050347','Color negro ubicado en soporte técnico','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(18,'294106440','no-break','Complet','MT805','21ZY080402','Color negro de 8 entradas AC (se encuentra en el área de soporte técnico)','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(37,'294106446','monitor','Dell','E2220H','CN00F0RPFCC00121C2VBA07','Monitor color negro de 21.5\" ubicado en soporte técnico','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(2,'294106693','monitor','Dell','E2016HV','CN07XJH5FCC00195A33IA14','Color negro (instalado en el lugar de alejandro)','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(14,'294107017','no-break','Complet','MT805','22ZY160515','No-Break color negro Complet','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(12,'294107026','teclado','Vorago','KB-502','01130221','Teclado gamer negro con LEDS rojas Vorago','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(30,'294107027','teclado','Vorgago','KB-502','NO VISIBLE','Teclado color negro ubicado en soporte técnico','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(3,'294107233','mouse','HP','HP125TPA','9CP32600VX','Mouse color negro HP','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(11,'294107236','teclado','Lenovo','SK-8823','90036934','Teclado color negro Lenovo','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(48,'29410728','teclado','vorogo','kb-502','806A1U8F','TECLADO COLOR NEGRO ALAMBRICO CON LUCES LED','Israel Almazan','soporte palacio nuevo'),(20,'294107384','monitor','Dell','E1916Hf','CN0XJ5TR7287267TC0KBA00','Color negro de 19.5\" (actualmente no esta en uso)','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(36,'294107549','monitor','Dell','E1916Hf','CN0XJ5TR72872685ANFUA00','Monitor color negro de 19.5\" ubicado en soporte técnico','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(17,'515101815','laptop','Dell','Latitude 3520','FQDFHS3','Laptop Dell  ubicado en soporte técnico ocupado por el usuario alahin toledo','alahin toledo','soporte palacio centro'),(27,'515102806','cpu','Lenovo','Thinkcentre M53','MJ03L7RS','Mini cpu color negro ubicado en el archivero color negro a lado del escritorio de Alejandro','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(24,'515102808','cpu','Lenovo','Thinkcentre M53','MJ03L2TN','Mini cpu color negro ubicado en el archivero color negro a lado del escritorio de Alejandro','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(23,'515102809','cpu','Lenovo','Thinkcentre m53','MJ03L2TW','Mini cpu color negro ubicado en el archivero color negro a lado del escritorio de Alejandro','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(22,'515103232','cpu','Gigabyte','GB-BACE-3150','1603634445','Minicpu color negro (ubicado en el anaquel de aluminio)','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(25,'515103362','laptop','Dell','Latitude E5470','jy80kc2','Laptop color negro (dañada del display) se encuentra ubicado en el archivero negro a lado del escritorio de Alejandro)','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(41,'515103587','iPad','Apple','5ta generación (A1822)','DMPTHDDSHLFF','Color blanco (frontal) y plateada (parte trasera)en el cajon de escritorio de Alejandro','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(42,'515103588','iPad','Apple','5ta generación (A1822)','DMPTHB41HLFF','Color blanco (parte frontal) y plateada (parte trasera) cajon de escritorio Alejandro','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(47,'515103593','iPad','apple','mp2j2cl/a','DMPTH8X1HLFF','TABLET CON FUNDA','Israel Almazan','soporte palacio nuevo'),(40,'515103596','iPad','Apple','5ta Generación','DMPTHAANHLFF','Color blanco (frontal) y plateado (trasera) esta asignado a Alejandro Lozano','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(43,'515103608','iPad','Apple','A1822','DMPTHCZLHLFF','Color plateada (parte trasera) y blanco (frontal) de 128 gb de almacenamiento (antes la tenia Yuli) Cuenta con un daño en la pantalla pero si funciona','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(39,'515104058','laptop','Dell','Latitude 3590','6g88cv2','Laptop color negro, intel core i5 7ma generación, 8 gb RAM, 128 gb SSD y 1 TB HDD','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(26,'515104123','laptop','Dell','Latitude 3590','140y1w2','Laptop color negro (dañada tarjeta lógica) se encuentra en el archivero negro a lado del escritorio de Alejandro.','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(32,'515104218','cpu','GHIA','Sin modelo','381445','cpu color negro y vistas plateadas ubicado en el anaquel de aluminio en soporte técnico','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(16,'515104312','laptop','Dell','G5 5500','1MRDF63','Procesador i7, RAM 16GB DDR4, SSD NVMe 480GB. Color negro','Jose David Olan Peraza','soporte palacio centro'),(45,'515104313','laptop','Dell','G5 15','P89F','Laptop G5 gaming','Manuel Lozano Reyes','soporte palacio nuevo'),(6,'515104316','mouse','HP','MOFYUO','S/N','Mouse color negro HP','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(7,'515104319','mouse','HP','MOFYUO','672652-001','Mouse color negro HP','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(31,'515104392','all in one','HP','21-b0015la','1CZ04102XZ','color blanco con teclado y mouse con el mismo nº de inventario (ubicado en el anaquel de aluminio de soporte)','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(33,'515104412','all in one','Lenovo','Ideacentre AIO 330-20IGM','YJ00KDM4','Color negro ubicado en el anaquel de aluminio de soporte técnico','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(46,'515104819','laptop','Dell','latitude 3520','30nfhs3','laptop color negro,cargador, disco solido,\r\nwindows 11','Israel Almazan','soporte palacio nuevo'),(35,'515104828','teclado','Dell','KB216t3','CN019M93LO30029FG6CUA02','Color negro ubicado en soporte técnico','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(38,'515105146','escaner','Epson','DS-970','X5Y4005191','Escaner color blanco (tamaño para escritorio de oficina individual) ubicado en soporte técnico','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(44,'515105248','laptop','Dell','Latitude 3440','JK48LY3','Color negro, pantalla de 14\", intel core i7 13va generación, 16 gb de RAM y 512 gb SSD Nvme','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(13,'515105254','teclado','DELL','KB216t3','S/N','Teclado color negro DELL','Manuel Alejandro Lozano Reyes','soporte palacio centro'),(1,'515105255','cpu','Asus','D700S','R3PFCG00N942112','Equipo color negro de gabinete con procesador intel core i7 12 va generación con 16 gb de memoria ram y 512 gb ssd. Cuenta con teclado de la misma marca y mouse, ambos color negro y mismo inventario del cpu','Manuel Alejandro Lozano Reyes','soporte palacio centro');
/*!40000 ALTER TABLE `inventario_soporte` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-27 12:07:50
