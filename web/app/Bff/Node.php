<?php

namespace App\Bff;

class Node
{
    /**
     * @param  array<string, mixed>  $props
     * @param  list<self>  $children
     * @param  array<string, mixed>|null  $onPress
     */
    public function __construct(
        public string $type,
        public ?string $id = null,
        public array $props = [],
        public array $children = [],
        public ?array $onPress = null,
    ) {}

    /**
     * @param  array<string, mixed>  $props
     * @param  list<self>  $children
     * @param  array<string, mixed>|null  $onPress
     */
    public static function make(string $type, array $props = [], array $children = [], ?array $onPress = null, ?string $id = null): self
    {
        $nodeId = $id ?? (isset($props['id']) && is_string($props['id']) ? $props['id'] : null);

        return new self($type, $nodeId, $props, $children, $onPress);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = ['type' => $this->type];
        if ($this->id !== null) {
            $payload['id'] = $this->id;
        }
        if ($this->props !== []) {
            $payload['props'] = $this->props;
        }
        if ($this->children !== []) {
            $payload['children'] = array_map(fn (self $child) => $child->toArray(), $this->children);
        }
        if ($this->onPress !== null) {
            $payload['onPress'] = $this->onPress;
        }

        return $payload;
    }
}
