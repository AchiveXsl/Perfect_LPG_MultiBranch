<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A posting that cannot be dated where the System Date puts it, because of what was already
 * recorded later (owner decision 2026-11-26). It carries what the user needs to understand and fix it:
 * a title, why it was blocked, the later transactions involved, and the workarounds.
 *
 * getMessage() is the whole explanation as plain text, so screens that only show a message still
 * explain it. The popup data travels in the X-LPG-Conflict response header (AJAX) or in the
 * flash entry system_date_conflict (form posts); the layout shows it as a popup.
 */
class SystemDateConflict extends RuntimeException
{
    public string $title;
    public string $why;
    /** @var array<int,array{label:string,date:string,detail?:string,url?:string}> */
    public array $items;
    /** @var array<int,array{text:string,action?:array}> */
    public array $solutions;

    public function __construct(string $title, string $why, array $items, array $solutions)
    {
        $this->title = $title;
        $this->why = $why;
        $this->items = $items;
        $this->solutions = $solutions;
        parent::__construct($this->plainText());
        $this->publish();
    }

    public function toArray(): array
    {
        return ['title' => $this->title, 'why' => $this->why, 'items' => $this->items, 'solutions' => $this->solutions];
    }

    public function plainText(): string
    {
        $lines = [$this->title . '.', 'Why: ' . $this->why];
        foreach ($this->items as $it) {
            $lines[] = '- ' . $it['label'] . ' on ' . $it['date'] . (!empty($it['detail']) ? ' (' . $it['detail'] . ')' : '');
        }
        $lines[] = 'What you can do:';
        foreach ($this->solutions as $i => $s) {
            $lines[] = ($i + 1) . '. ' . $s['text'];
        }
        return implode("\n", $lines);
    }

    /** Hand the popup data to the response, whichever way the screen reads errors. */
    protected function publish(): void
    {
        if (is_cli()) {
            return;
        }
        try {
            $json = json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
            $request = service('request');
            $ajax = strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest'
                || str_contains(strtolower($request->getHeaderLine('Accept')), 'application/json');
            if ($ajax) {
                service('response')->setHeader('X-LPG-Conflict', base64_encode((string) $json));
            } else {
                session()->setFlashdata('system_date_conflict', $json);
            }
        } catch (\Throwable $e) {
            // The plain message still explains it.
        }
    }
}
