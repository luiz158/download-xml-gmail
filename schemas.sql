
CREATE TABLE IF NOT EXISTS `XML_NF` (
  `CHAVE_ACESSO` VARCHAR(50) NOT NULL,
  `CONTEUDO_XML` LONGTEXT,
  `DATA_EMI` DATETIME DEFAULT NULL,
  `RECEBIDO_POR` VARCHAR(255) DEFAULT NULL,
  `EMIT_NOME` VARCHAR(255) DEFAULT NULL,
  `EMIT_CNPJ` VARCHAR(255) DEFAULT NULL,
  `DEST_CNPJ` VARCHAR(255) DEFAULT NULL,
  `DATA_IMPORTACAO` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`CHAVE_ACESSO`)
) ENGINE=INNODB DEFAULT CHARSET=LATIN1;
