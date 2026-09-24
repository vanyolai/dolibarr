CREATE TABLE llx_completioncertificate (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL DEFAULT 1,
 ref varchar(30) NOT NULL,
 fk_soc integer NOT NULL,
 fk_commande integer NOT NULL,
 date_completion date NOT NULL,
 note_public text,
 status smallint NOT NULL DEFAULT 0,
 fk_user_author integer,
 fk_user_valid integer,
 datec datetime NOT NULL,
 tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uk_completioncertificate_ref_entity (ref, entity),
 INDEX idx_completioncertificate_order (fk_commande),
 INDEX idx_completioncertificate_soc (fk_soc)
) ENGINE=innodb;
