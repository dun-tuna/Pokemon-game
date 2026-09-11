<?php

declare(strict_types=1);

/**
 * The classes accepted by the intentionally vulnerable save loader.
 * They are deliberately free of magic methods and side-effect gadgets.
 */
class Trainer
{
    public $name;
    public $starter;
    public $type;
    public $hp;
    public $damage;

    public function __construct(string $name, string $starter)
    {
        $profile = self::profile($starter);
        $this->name = $name;
        $this->starter = $starter;
        $this->type = $profile['type'];
        $this->hp = 100;
        $this->damage = $profile['damage'];
    }

    public static function profile(string $starter): array
    {
        $profiles = [
            'charmander' => ['type' => 'fire', 'damage' => 62, 'image' => 'charmander.png'],
            'bulbasaur' => ['type' => 'grass', 'damage' => 60, 'image' => 'bulbasaur.png'],
            'squirtle' => ['type' => 'water', 'damage' => 64, 'image' => 'squirtle.png'],
        ];

        return $profiles[$starter] ?? $profiles['charmander'];
    }

}
