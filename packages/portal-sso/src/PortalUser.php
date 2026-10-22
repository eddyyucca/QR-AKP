<?php

namespace Akp\PortalSso;

use ArrayAccess;
use LogicException;

/**
 * Pengguna yang sedang login lewat Portal. Dibentuk dari klaim token SSO dan disimpan di sesi.
 *
 * Dapat dibaca sebagai objek ($user->name) maupun array ($user['name']).
 *
 * @property-read string $id            ID akun di Portal
 * @property-read ?string $username
 * @property-read ?string $employee_no  NIK karyawan (kunci relasi ke data karyawan), null untuk akun non-karyawan
 * @property-read string $name
 * @property-read ?string $email
 * @property-read string $role          kode level di aplikasi ini (mis. admin, hr, viewer)
 * @property-read int $level            tingkat level (lebih besar = hak lebih besar)
 * @property-read bool $is_admin        super admin Portal
 */
final class PortalUser implements ArrayAccess
{
    private function __construct(private readonly array $data)
    {
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /** Dari klaim token SSO. */
    public static function fromClaims(array $claims): self
    {
        return new self([
            'id' => (string) $claims['sub'],
            'username' => $claims['usr'] ?? null,
            'employee_no' => $claims['emp'] ?? null,
            'name' => $claims['name'],
            'email' => $claims['email'] ?? null,
            'role' => $claims['role'],
            'level' => (int) ($claims['level'] ?? 0),
            'is_admin' => (bool) ($claims['admin'] ?? false),
        ]);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    /** Level pengguna minimal $level? Super admin Portal selalu lolos. */
    public function atLeast(int $level): bool
    {
        return $this->is_admin || $this->level >= $level;
    }

    /** Apakah kode level pengguna salah satu dari $roles? Super admin Portal selalu lolos. */
    public function hasRole(string ...$roles): bool
    {
        return $this->is_admin || in_array($this->role, $roles, true);
    }

    public function __get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('PortalUser bersifat read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('PortalUser bersifat read-only.');
    }
}