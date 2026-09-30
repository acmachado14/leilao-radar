<?php

namespace App\Bff;

class ScreenDocument
{
    /**
     * @param  list<Node>  $components
     * @param  list<array<string, mixed>>  $tabs
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $screen,
        public string $title,
        public array $components,
        public ?array $refresh = ['type' => ActionType::RELOAD_SCREEN],
        public array $tabs = [],
        public array $meta = [],
        public ?string $token = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'schema_version' => (int) config('radar.bff.schema_version', 1),
            'screen' => $this->screen,
            'title' => $this->title,
            'refresh' => $this->refresh,
            'components' => array_map(fn (Node $node) => $node->toArray(), $this->components),
            'tabs' => $this->tabs,
            'meta' => $this->meta,
        ];

        if ($this->token !== null) {
            $payload['token'] = $this->token;
        }

        return $payload;
    }
}
