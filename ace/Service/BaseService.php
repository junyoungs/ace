<?php declare(strict_types=1);

namespace ACE\Service;

use ACE\Database\DB;

/**
 * BaseService - Simple helper for custom business logic
 *
 * Provides transaction management and basic validation.
 * Extend this class for Services that need multi-table operations.
 */
abstract class BaseService
{
    /**
     * Execute operations within a database transaction
     *
     * @param callable $callback The operations to execute
     * @return mixed Result from callback
     * @throws \Exception If transaction fails
     */
    protected function transaction(callable $callback)
    {
        $db = DB::getInstance();
        try {
            $db->beginTransaction();
            $result = $callback();
            $db->commit();
            return $result;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Simple field validation
     *
     * @param array $data Data to validate
     * @param array $rules Validation rules ['field' => 'required']
     * @return void
     * @throws \Exception If validation fails
     */
    protected function validate(array $data, array $rules): void
    {
        foreach ($rules as $field => $rule) {
            if ($rule === 'required' && empty($data[$field])) {
                throw new \Exception("Field {$field} is required");
            }
        }
    }
}
