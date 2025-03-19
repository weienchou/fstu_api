<?php
namespace App\Validators;

use App\Exceptions\ValidationException;
use Respect\Validation\Validator as v;

class RequestValidator
{
    private $rules = [];
    private $error_messages = [];
    private $validated_data = [];
    public function setRules(array $rules): self
    {
        $this->rules = $rules;
        return $this;
    }
    public function setErrorMessages(array $messages): self
    {
        $this->error_messages = $messages;
        return $this;
    }
    /**
     * Validate request parameters against defined rules
     *
     * @param array $params Parameters to validate
     * @return array Validated and filtered parameters
     * @throws ValidationException If validation fails
     */
    public function validate(array $params): array
    {
        $errors = [];
        foreach ($this->rules as $param => $rule) {
            if (isset($params[$param])) {
                try {
                    $rule->assert($params[$param]);
                    $this->validated_data[$param] = $params[$param];
                } catch (\Respect\Validation\Exceptions\ValidationException $e) {
                    dd($e);
                    $errors[$param] = $this->formatError($param, $e);
                }
            } else if ($rule->hasRule('required') || $rule->hasRule('notOptional')) {
                $errors[$param] = $this->getCustomMessage($param, 'required') ?? 'The ' . $param . ' field is required';
            }
        }

        if (!empty($errors)) {
            dd($errors);
            throw new ValidationException(500, $errors);
        }

        return $this->validated_data;
    }
    /**
     * Format validation error message
     *
     * @param string $field Field name
     * @param \Respect\Validation\Exceptions\ValidationException $exception
     * @return string
     */
    private function formatError(string $field, \Respect\Validation\Exceptions\ValidationException $exception): string
    {
        // 修正：使用正確的方法獲取錯誤訊息
        // 選項 1: 使用 getMessage() 方法
        $default = $exception->getMessage();

        // 選項 2 (如果上面的不起作用): 嘗試使用 getMessages() 方法並獲取第一條訊息
        // $messages = $exception->getMessages();
        // $default = !empty($messages) ? reset($messages) : 'Validation failed';

        return $this->getCustomMessage($field, $exception->getId()) ?? $default;
    }
    /**
     * Get custom error message if defined
     *
     * @param string $field Field name
     * @param string $rule Rule identifier
     * @return string|null
     */
    private function getCustomMessage(string $field, string $rule): ?string
    {
        if (isset($this->error_messages[$field][$rule])) {
            return $this->error_messages[$field][$rule];
        }
        if (isset($this->error_messages[$field]['default'])) {
            return $this->error_messages[$field]['default'];
        }
        return null;
    }
    /**
     * Get validated data
     *
     * @return array
     */
    public function getValidatedData(): array
    {
        return $this->validated_data;
    }
}
