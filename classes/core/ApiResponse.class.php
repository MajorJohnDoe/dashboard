<?php
namespace Dashboard\Core;

class ApiResponse
{
    private bool $success;
    private string $message;
    private array $data;
    private array $triggers;
    private int $statusCode;

    public function __construct(
        bool $success = false,
        string $message = '',
        array $data = [],
        array $triggers = []
    ) {
        $this->success = $success;
        $this->message = $message;
        $this->data = $data;
        $this->triggers = $triggers;
        $this->statusCode = $success ? 200 : 400;
    }

    public static function success(string $message = 'Success', array $data = [], array $triggers = []): self
    {
        return new self(true, $message, $data, $triggers);
    }

    public static function error(string $message = 'An error occurred', array $data = [], int $statusCode = 400): self
    {
        $response = new self(false, $message, $data);
        $response->statusCode = $statusCode;
        return $response;
    }

    public function withTriggers(array $triggers): self
    {
        $this->triggers = array_merge($this->triggers, $triggers);
        return $this;
    }

    public function withData(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data
        ]);
    }

    public function triggerHtmx(): void
    {
        if (!empty($this->triggers)) {
            header('HX-Trigger: ' . json_encode($this->triggers));
        }
    }

    public function sendWithHtmx(): void
    {
        $this->triggerHtmx();
        $this->send();
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getTriggers(): array
    {
        return $this->triggers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data
        ];
    }

    public function __toString(): string
    {
        return json_encode($this->toArray());
    }
}
