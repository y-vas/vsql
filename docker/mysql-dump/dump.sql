
CREATE TABLE Users (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `type` VARCHAR(45) NULL,
  `num` INT(45) NULL,
  `email` VARCHAR(45) NULL,
  `name` VARCHAR(45) NULL,
  `surname` VARCHAR(45) NULL,
  `phone` VARCHAR(45) NULL
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Products (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(45) NULL,
  `type` INT(45) NULL,
  `num` INT NULL,
  `user_id` INT NULL,
  `cost` INT NOT NULL
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

INSERT INTO Users (id, type, num, email, name, surname, phone )
VALUES (1, 'ADMIN' ,123,'yovdiyvasyl@gmail.com','vas','yo','444444444');

INSERT INTO Users (id, type, num, email, name, surname, phone )
VALUES (1, 'customer' ,1,'yovdiyvasyl+test@gmail.com','tvas','tyo','555555555');

INSERT INTO Products (id, name, type, num, user_id, cost ) VALUES
(1, 'meat' ,2,124    ,1 , 20   ),
(2, 'fruit',2231124  ,1 , 5    ),
(3, 'car'  ,11238    ,1 , 20000),
(1, 'pen'  ,3,1123531,1 , 1    );
