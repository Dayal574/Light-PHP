<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Optional layout renderer exposed to views as $template.
 * Pages can ignore it and keep using plain PHP or get_header()/get_footer().
 */
final class Template
{
    private static ?self $current = null;

    private static ?string $onceRequest = null;

    /** @var array<string, true> */
    private static array $onceIds = [];

    private string $markPrefix;

    /** @var list<array<string, mixed>> */
    private array $stack = [];

    /** @var array<string, string> */
    private array $sections = [];

    /** @var array<string, string> */
    private array $stacks = [];

    /** @var array<string, string> */
    private array $fragments = [];

    /** @var list<string> */
    private array $renderingFiles = [];

    private ?string $pendingLayout = null;

    private int $renderDepth = 0;

    public function __construct()
    {
        $this->markPrefix = '@@tplparent' . bin2hex(random_bytes(8));
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    /** @param array<string, mixed> $data */
    public function render(string $file, array $data = []): string
    {
        $start = ob_get_level();
        $previous = self::$current;
        self::$current = $this;
        $this->renderDepth++;
        $this->renderingFiles[] = $file;

        try {
            ob_start();
            $this->includeFile($file, $data);

            if ($this->stack !== []) {
                $open = $this->stack[array_key_last($this->stack)];
                throw new RuntimeException(sprintf(
                    "Close the open %s '%s' with end().",
                    (string) $open['type'],
                    (string) $open['name']
                ));
            }

            $html = (string) ob_get_clean();
            $next = $this->pendingLayout;
            $this->pendingLayout = null;

            if ($next !== null) {
                return $this->render($this->resolveUserView($next, 'Layout'), $data);
            }

            if ($this->renderDepth === 1) {
                $html = $this->applyInlineMarkers($html);
            }

            return $html;
        } catch (Throwable $exception) {
            while (ob_get_level() > $start) {
                ob_end_clean();
            }

            $this->stack = [];
            $this->pendingLayout = null;
            throw $exception;
        } finally {
            array_pop($this->renderingFiles);
            $this->renderDepth--;
            self::$current = $previous;
        }
    }

    public function layout(string $name): void
    {
        foreach ($this->stack as $frame) {
            if ($frame['type'] === 'component' || $frame['type'] === 'slot') {
                throw new RuntimeException('Call layout() from the page or a layout.');
            }
        }

        if ($this->pendingLayout !== null) {
            throw new RuntimeException('layout() was already called.');
        }

        $file = $this->resolveUserView($name, 'Layout');
        if (in_array($file, $this->renderingFiles, true)) {
            throw new RuntimeException('Layout loop detected: ' . $name);
        }

        $this->pendingLayout = $name;
    }

    public function section(string $name, ?string $content = null): void
    {
        $this->assertLabel($name);
        if (func_num_args() >= 2) {
            $this->storeSection($name, (string) $content, 'replace');
            return;
        }

        $this->assertSectionClosed($name);
        $this->open('section', $name);
    }

    public function append(string $name): void
    {
        $this->assertLabel($name);
        $this->assertSectionClosed($name);
        $this->open('append', $name);
    }

    public function prepend(string $name): void
    {
        $this->assertLabel($name);
        $this->assertSectionClosed($name);
        $this->open('prepend', $name);
    }

    public function yield(string $name, string $default = ''): void
    {
        $this->assertLabel($name);
        if (
            $this->renderDepth === 1
            && $this->pendingLayout === null
            && isset($this->inlineMarkers[$name])
        ) {
            return;
        }

        echo $this->clearParentMarks($this->sections[$name] ?? $default);
    }

    public function hasSection(string $name): bool
    {
        $this->assertLabel($name);

        return array_key_exists($name, $this->sections);
    }

    public function parent(): void
    {
        $name = $this->openSectionName();
        if ($name === null) {
            throw new RuntimeException('parent() can only be used inside a section.');
        }

        echo $this->parentMark($name);
    }

    public function push(string $name): void
    {
        $this->assertLabel($name);
        $this->open('push', $name);
    }

    public function stack(string $name): void
    {
        $this->assertLabel($name);
        echo $this->stacks[$name] ?? '';
    }

    /** @param array<string, mixed> $data */
    public function component(string $name, array $data = []): void
    {
        resolve_view_file('components', $name);
        $this->stack[] = [
            'type' => 'component',
            'name' => $name,
            'phase' => 'fill',
            'data' => $data,
            'slots' => [],
        ];
        ob_start();
    }

    public function slot(?string $name = null): void
    {
        $top = $this->stack === [] ? null : $this->stack[array_key_last($this->stack)];

        if ($top !== null && $top['type'] === 'slot') {
            throw new RuntimeException('Close the open slot with end() before opening another.');
        }

        if ($top !== null && $top['type'] === 'component' && $top['phase'] === 'fill') {
            if ($name === null || $name === '') {
                throw new RuntimeException('Give the slot a name.');
            }

            $this->assertLabel($name);
            $this->open('slot', $name);
            return;
        }

        $component = $this->nearestRenderedComponent();
        if ($component === null) {
            throw new RuntimeException('slot() can only be used with a component.');
        }

        $key = ($name === null || $name === '') ? 'default' : $name;
        echo (string) ($component['slots'][$key] ?? '');
    }

    public function hasSlot(?string $name = null): bool
    {
        $component = $this->nearestRenderedComponent();
        if ($component === null) {
            return false;
        }

        $key = ($name === null || $name === '') ? 'default' : $name;

        return array_key_exists($key, $component['slots']);
    }

    /**
     * Default component variables. Passed data wins.
     * The renderer turns this call into extract(), so the keys become variables.
     *
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    public function props(array $defaults): array
    {
        $component = $this->nearestRenderedComponent();
        if ($component === null) {
            throw new RuntimeException('props() can only be used inside a component.');
        }

        $values = $this->viewData($defaults);
        foreach ($this->viewData($component['data']) as $key => $value) {
            $values[$key] = $value;
        }

        return $values;
    }

    /** @param array<string, mixed> $data */
    public function include(string $name, array $data = []): void
    {
        $this->includeFile($this->resolveUserView($name, 'View'), $data);
    }

    public function once(string $name): void
    {
        $this->assertLabel($name);
        $this->open('once', $name);
    }

    /** @param array<int|string, mixed> $classes */
    public function classes(array $classes): string
    {
        $names = [];
        foreach ($classes as $key => $value) {
            if (is_int($key)) {
                if ($value === false || $value === null || $value === '') {
                    continue;
                }
                $names[] = (string) $value;
                continue;
            }

            if ($value) {
                $names[] = (string) $key;
            }
        }

        return implode(' ', $names);
    }

    public function fragment(string $name): void
    {
        $this->assertLabel($name);
        $this->open('fragment', $name);
    }

    public function renderFragment(string $name): void
    {
        $this->assertLabel($name);
        echo $this->fragments[$name] ?? '';
    }

    public function end(): void
    {
        if ($this->stack === []) {
            throw new RuntimeException('end() was called without an open block.');
        }

        $frame = array_pop($this->stack);
        $content = ob_get_clean();
        if ($content === false) {
            throw new RuntimeException('end() could not close the open block.');
        }

        switch ($frame['type']) {
            case 'section':
                $this->storeSection((string) $frame['name'], $content, 'replace');
                return;
            case 'append':
                $this->storeSection((string) $frame['name'], $content, 'append');
                return;
            case 'prepend':
                $this->storeSection((string) $frame['name'], $content, 'prepend');
                return;
            case 'push':
                $name = (string) $frame['name'];
                $this->stacks[$name] = ($this->stacks[$name] ?? '') . $content;
                return;
            case 'once':
                $this->finishOnce((string) $frame['name'], $content);
                return;
            case 'fragment':
                $this->fragments[(string) $frame['name']] = $content;
                return;
            case 'slot':
                $this->storeSlot((string) $frame['name'], $content);
                return;
            case 'component':
                $frame['slots']['default'] = $content;
                $this->renderFilledComponent($frame);
                return;
        }

        throw new RuntimeException('Unknown template block.');
    }

    private function open(string $type, string $name): void
    {
        $this->stack[] = ['type' => $type, 'name' => $name];
        ob_start();
    }

    private function storeSection(string $name, string $content, string $mode): void
    {
        if ($mode === 'append') {
            $this->sections[$name] = ($this->sections[$name] ?? '') . $content;
            $this->markInlineSection($name);
            return;
        }

        if ($mode === 'prepend') {
            $this->sections[$name] = $content . ($this->sections[$name] ?? '');
            $this->markInlineSection($name);
            return;
        }

        if (array_key_exists($name, $this->sections)) {
            $mark = $this->parentMark($name);
            if (str_contains($this->sections[$name], $mark)) {
                $this->sections[$name] = str_replace($mark, $content, $this->sections[$name]);
            }
            return;
        }

        $this->sections[$name] = $content;
        $this->markInlineSection($name);
    }

    /** @var array<string, true> */
    private array $inlineMarkers = [];

    private function markInlineSection(string $name): void
    {
        if ($this->renderDepth !== 1 || $this->pendingLayout !== null || isset($this->inlineMarkers[$name])) {
            return;
        }

        $this->inlineMarkers[$name] = true;
        echo $this->outputMark($name);
    }

    private function applyInlineMarkers(string $html): string
    {
        foreach (array_keys($this->inlineMarkers) as $name) {
            $html = str_replace(
                $this->outputMark($name),
                $this->clearParentMarks($this->sections[$name] ?? ''),
                $html
            );
        }

        return $html;
    }

    private function outputMark(string $name): string
    {
        return $this->markPrefix . '_out_' . $name . '@@';
    }

    private function finishOnce(string $name, string $content): void
    {
        $request = (string) ($_SERVER['REQUEST_TIME_FLOAT'] ?? 'cli');
        if (self::$onceRequest !== $request) {
            self::$onceRequest = $request;
            self::$onceIds = [];
        }

        if (isset(self::$onceIds[$name])) {
            return;
        }

        self::$onceIds[$name] = true;
        echo $content;
    }

    private function storeSlot(string $name, string $content): void
    {
        for ($index = count($this->stack) - 1; $index >= 0; $index--) {
            if ($this->stack[$index]['type'] === 'component' && $this->stack[$index]['phase'] === 'fill') {
                $this->stack[$index]['slots'][$name] = $content;
                return;
            }
        }

        throw new RuntimeException('A slot was closed outside a component.');
    }

    /** @param array<string, mixed> $frame */
    private function renderFilledComponent(array $frame): void
    {
        $frame['phase'] = 'render';
        $this->stack[] = $frame;
        $marker = array_key_last($this->stack);

        try {
            $file = resolve_view_file('components', (string) $frame['name']);
            $this->includeFile($file, $frame['data']);

            if (array_key_last($this->stack) !== $marker) {
                $open = $this->stack[array_key_last($this->stack)];
                throw new RuntimeException(sprintf(
                    "Close the open %s '%s' with end().",
                    (string) $open['type'],
                    (string) $open['name']
                ));
            }
        } finally {
            if (isset($this->stack[$marker]) && ($this->stack[$marker]['phase'] ?? null) === 'render') {
                array_splice($this->stack, (int) $marker, 1);
            }
        }
    }

    /** @return array<string, mixed>|null */
    private function nearestRenderedComponent(): ?array
    {
        for ($index = count($this->stack) - 1; $index >= 0; $index--) {
            $frame = $this->stack[$index];
            if ($frame['type'] === 'component' && $frame['phase'] === 'render') {
                return $frame;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $data */
    private function includeFile(string $file, array $data): void
    {
        $template = $this;
        $data = $this->viewData($data);
        $source = file_get_contents($file);
        if ($source !== false && str_contains($source, '$template->props')) {
            $this->includeWithProps($file, $source, $data);
            return;
        }

        extract($data, EXTR_SKIP);
        require $file;
    }

    /** @param array<string, mixed> $data */
    private function includeWithProps(string $file, string $source, array $data): void
    {
        $template = $this;
        extract($data, EXTR_SKIP);
        $rewritten = $this->rewriteMagicConstants($this->rewritePropsCalls($source), $file);
        $temporary = tempnam(sys_get_temp_dir(), 'tpl');
        if ($temporary === false) {
            throw new RuntimeException('Unable to render component props.');
        }

        $script = $temporary . '.php';
        file_put_contents($script, $rewritten);

        try {
            require $script;
        } finally {
            @unlink($script);
            @unlink($temporary);
        }
    }

    private function rewritePropsCalls(string $source): string
    {
        $pattern = '/\$template->props\s*\(/';
        $offset = 0;
        $result = '';

        while (preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $match[0][1];
            $before = substr($source, max(0, $start - 7), 7);
            $open = $start + strlen($match[0][0]) - 1;
            $close = $this->matchingParen($source, $open);
            $call = substr($source, $start, $close - $start + 1);
            $result .= substr($source, $offset, $start - $offset);

            if ($before === 'extract') {
                $result .= $call;
            } else {
                $result .= 'extract(' . $call . ', EXTR_SKIP)';
            }

            $offset = $close + 1;
        }

        return $result . substr($source, $offset);
    }

    private function matchingParen(string $source, int $open): int
    {
        $length = strlen($source);
        $depth = 0;
        $quote = null;

        for ($index = $open; $index < $length; $index++) {
            $character = $source[$index];

            if ($quote !== null) {
                if ($character === '\\' && ($quote !== "'" || ($source[$index + 1] ?? '') === "'" || ($source[$index + 1] ?? '') === '\\')) {
                    $index++;
                    continue;
                }
                if ($character === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
                continue;
            }

            if ($character === '(') {
                $depth++;
                continue;
            }

            if ($character === ')') {
                $depth--;
                if ($depth === 0) {
                    return $index;
                }
            }
        }

        throw new RuntimeException('Unclosed props() call.');
    }

    private function rewriteMagicConstants(string $source, string $file): string
    {
        $tokens = token_get_all($source);
        $rewritten = '';

        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_DIR) {
                $rewritten .= var_export(dirname($file), true);
                continue;
            }
            if (is_array($token) && $token[0] === T_FILE) {
                $rewritten .= var_export($file, true);
                continue;
            }

            $rewritten .= is_array($token) ? $token[1] : $token;
        }

        return $rewritten;
    }

    /** @param array<mixed, mixed> $data
     *  @return array<string, mixed>
     */
    private function viewData(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            if (!is_string($key) || $key === 'template' || $key === 'this') {
                continue;
            }
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }
            $clean[$key] = $value;
        }

        return $clean;
    }

    private function resolveUserView(string $name, string $label): string
    {
        try {
            return resolve_view_file('', $name);
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'not found')) {
                throw new RuntimeException($label . ' not found: ' . $name, 0, $exception);
            }

            throw new RuntimeException('Invalid ' . strtolower($label) . ' name: ' . $name, 0, $exception);
        }
    }

    private function assertLabel(string $name): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $name)) {
            throw new RuntimeException('Invalid name: ' . $name);
        }
    }

    private function assertSectionClosed(string $name): void
    {
        foreach ($this->stack as $frame) {
            if (in_array($frame['type'], ['section', 'append', 'prepend'], true) && $frame['name'] === $name) {
                throw new RuntimeException("Section '{$name}' is already open.");
            }
        }
    }

    private function openSectionName(): ?string
    {
        for ($index = count($this->stack) - 1; $index >= 0; $index--) {
            if (in_array($this->stack[$index]['type'], ['section', 'append', 'prepend'], true)) {
                return (string) $this->stack[$index]['name'];
            }
        }

        return null;
    }

    private function parentMark(string $name): string
    {
        return $this->markPrefix . '_' . $name . '@@';
    }

    private function clearParentMarks(string $content): string
    {
        $cleared = preg_replace(
            '/' . preg_quote($this->markPrefix, '/') . '_[A-Za-z_][A-Za-z0-9_-]*@@/',
            '',
            $content
        );

        return $cleared ?? $content;
    }
}
