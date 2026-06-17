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
-- Table structure for table `carpetas_nas`
--

DROP TABLE IF EXISTS `carpetas_nas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `carpetas_nas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ubicacion` varchar(255) NOT NULL,
  `nombre_carpeta` varchar(100) NOT NULL,
  `ip_servidor` varchar(50) DEFAULT NULL,
  `usuario` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `carpetas_nas`
--

LOCK TABLES `carpetas_nas` WRITE;
/*!40000 ALTER TABLE `carpetas_nas` DISABLE KEYS */;
INSERT INTO `carpetas_nas` VALUES (1,'Catastro','Proyectos','172.16.10.38','Administrador','Temporal9catastro','','2025-11-28 00:20:37'),(2,'Ingresos','Server','172.16.10.85','soporte','St4nl3y01',NULL,'2025-11-28 00:20:37'),(3,'Ingresos','Departamento de control de obligaciones','172.16.10.85','enriqueta.juarez',NULL,NULL,'2025-11-28 00:20:37'),(4,'Ingresos','Departamento de recaudacion','172.16.10.85','leydi.badal',NULL,NULL,'2025-11-28 00:20:37'),(5,'Ingresos','Departamento de recepcion','172.16.10.85','aldo.guerra',NULL,NULL,'2025-11-28 00:20:37'),(6,'Ingresos','Departamento de impuestos sobre la adquisicion','172.16.10.85','teltzin.fuentes',NULL,NULL,'2025-11-28 00:20:37'),(7,'Ingresos','Departamento de recepcion','172.16.10.85','oscar.ortiz',NULL,NULL,'2025-11-28 00:20:37'),(8,'Ingresos','Departamento de impuestos predial','172.16.10.85','francisco.aranda',NULL,NULL,'2025-11-28 00:20:37'),(9,'Ingresos','Departamento administrativo y recursos humanos','172.16.10.85','claudia.canto',NULL,NULL,'2025-11-28 00:20:37'),(10,'Ingresos','Departamento de recaudacion','172.16.10.85','oscar.torres','Ingrerec2025',NULL,'2025-11-28 00:20:37'),(11,'Ingresos','Departamento de recaudacion','172.16.10.85','juan.vargas','Ingreglo2025',NULL,'2025-11-28 00:20:37'),(12,'Ingresos','Departamento juridico','172.16.10.85','lilu.lopez','Juringres2025',NULL,'2025-11-28 00:20:37'),(13,'Ingresos','Todos los departamentos','172.16.10.85','alejandra.madrigal',NULL,NULL,'2025-11-28 00:20:37'),(14,'Ingresos','Facturacion y altas al padron','172.16.10.85','eleazar.martinez',NULL,NULL,'2025-11-28 00:20:37'),(15,'Contabilidad','soporte','172.16.10.31','manuel.lozano','St4nl3y01',NULL,'2025-11-28 00:20:37'),(16,'Contabilidad','contabilidad2022','172.16.10.31','adriana_esquivel','4dr14n42022',NULL,'2025-11-28 00:20:37'),(17,'Contabilidad','contabilidad2022','172.16.10.31','angeles_basto','4ng3l3$2022',NULL,'2025-11-28 00:20:37'),(18,'Contabilidad','contabilidad2022','172.16.10.31','elena_ix','3l3n42022',NULL,'2025-11-28 00:20:37'),(19,'Contabilidad','contabilidad2022','172.16.10.31','emilia_perez','3m1l142022',NULL,'2025-11-28 00:20:37'),(20,'Contabilidad','contabilidad2022','172.16.10.31','hector_tamayo','H3ct0r2022',NULL,'2025-11-28 00:20:37'),(21,'Contabilidad','contabilidad2022','172.16.10.31','jaime_nieto','J41m32022',NULL,'2025-11-28 00:20:37'),(22,'Contabilidad','contabilidad2022','172.16.10.31','juana_martinez','Ju4n42022',NULL,'2025-11-28 00:20:37'),(23,'Contabilidad','contabilidad2022','172.16.10.31','luis_basto','Lu1$2022',NULL,'2025-11-28 00:20:37'),(24,'Contabilidad','contabilidad2022','172.16.10.31','oneida_ubaldo','Ubaldo306',NULL,'2025-11-28 00:20:37'),(25,'Contabilidad','contabilidad2022','172.16.10.31','maria_rosas','Temporal10',NULL,'2025-11-28 00:20:37'),(26,'Contabilidad','contabilidad2022','172.16.10.31','manuel_mediero','Temporal102',NULL,'2025-11-28 00:20:37'),(27,'Contabilidad','contabilidad2022','172.16.10.31','seydibarrera','Contabilidad21',NULL,'2025-11-28 00:20:37'),(28,'DNTICS','Compartidaaa','192.168.25.20','Emmanuel','12123132','','2025-12-02 18:30:18'),(29,'PRUEBA','PRUEBA','1715151','DSDFSF','VGHVGHV','NA','2025-12-05 20:00:17');
/*!40000 ALTER TABLE `carpetas_nas` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-27 12:07:47
