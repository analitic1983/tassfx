<?php

declare(strict_types=1);

use yii\db\Migration;
use yii\db\Query;

/**
 * Class m220316_140121_9123_profile_bank_card
 */
class m220316_140121_9123_profile_bank_card extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->execute(
            "
 CREATE TABLE `profile_bank_card` (
  `uuid` CHAR(36) NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `last_digits` VARCHAR(8) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `front_photo_file` VARCHAR(255) ,
  `back_photo_file` VARCHAR(255),
   status ENUM(
        'draft',
        'waitForApprove',
        'approved',
        'declined',
        'deleted',
        'deletedByModerator'
    ) NOT NULL,
  PRIMARY KEY (`uuid`),
  KEY `fk_profile_bank_card_user_id_idx` (`user_id`),
  CONSTRAINT `fk_profile_bank_card_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB;
"
        );
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $this->dropTable('profile_bank_card');
    }

}
