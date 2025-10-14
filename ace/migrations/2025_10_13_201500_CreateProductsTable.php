<?php declare(strict_types=1);

class CreateProductsTable
{
    /**
     * The database connection instance.
     * @var \DATABASE\DatabaseDriverInterface
     */
    public $db;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $sql = "
            CREATE TABLE `products` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `price` DECIMAL(10, 2) NOT NULL,
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ";
        $this->db->query($sql);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $sql = "DROP TABLE IF EXISTS `products`;";
        $this->db->query($sql);
    }
}