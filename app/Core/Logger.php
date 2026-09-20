<?php
declare(strict_types=1);
namespace App\Core;

final class Logger
{
    private const LEVELS = ['debug'=>0,'info'=>1,'notice'=>2,'warning'=>3,'error'=>4,'critical'=>5];
    private const REDACT_KEYS = ['password','token','secret','authorization','key','hash','bearer'];

    private int    $minLevel;
    private string $logDir;

    public function __construct(string $logDir, string $level = 'warning')
    {
        $this->logDir   = rtrim($logDir, '/\\');
        $this->minLevel = self::LEVELS[strtolower($level)] ?? 3;
    }

    public function debug(string $msg, array $ctx = []): void   { $this->write('debug',   $msg, $ctx); }
    public function info(string $msg, array $ctx = []): void    { $this->write('info',    $msg, $ctx); }
    public function notice(string $msg, array $ctx = []): void  { $this->write('notice',  $msg, $ctx); }
    public function warning(string $msg, array $ctx = []): void { $this->write('warning', $msg, $ctx); }
    public function error(string $msg, array $ctx = []): void   { $this->write('error',   $msg, $ctx); }
    public function critical(string $msg, array $ctx = []): void{ $this->write('critical',$msg, $ctx); }

    private function write(string $level, string $message, array $context): void
    {
        if ((self::LEVELS[$level] ?? 0) < $this->minLevel) {
            return; // Below minimum level — skip
        }

        $entry = json_encode([
            'ts'         => date('c'),
            'level'      => $level,
            'msg'        => $message,
            'request_id' => RequestId::current(),
            'ctx'        => $this->redact($context),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $file = $this->logDir . '/app-' . date('Y-m-d') . '.log';

        // Append — no locking needed for single-line writes (atomic on most OS)
        file_put_contents($file, $entry . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Recursively redact sensitive key values.
     */
    private function redact(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $result = [];
        foreach ($data as $key => $value) {
            $lowerKey = is_string($key) ? strtolower($key) : '';
            $isSensitive = false;
            foreach (self::REDACT_KEYS as $redactKey) {
                if (str_contains($lowerKey, $redactKey)) {
                    $isSensitive = true;
                    break;
                }
            }
            $result[$key] = $isSensitive ? '***' : $this->redact($value);
        }
        return $result;
    }
}
