<?php

namespace Tests\Support;

/**
 * Monta valores com cara de segredo só em memória, para os testes do
 * redator. O fonte não guarda os padrões contínuos que o GitHub bloqueia.
 */
final class SecretFixtures
{
    public static function awsAccessKey(): string
    {
        return 'AKI'.'A'.'IOSFODNN7EXAMPLE';
    }

    public static function githubClassic(): string
    {
        return 'gh'.'p_'.str_repeat('a', 36);
    }

    public static function githubClassicPhase3(): string
    {
        return 'gh'.'p_'.'notarealtokenvalue00000000001111';
    }

    public static function githubFineGrained(): string
    {
        return 'github'.'_pat_'.'11AAAAAAA01234567890_abcdefghij';
    }

    public static function openaiKey(): string
    {
        return 'sk'.'-'.str_repeat('abcdefghijklmnopqrstuvwxyz012345', 1);
    }

    public static function googleApiKey(): string
    {
        return 'AI'.'za'.'SyDaGmWKa4JsXZ-HjGw7ISLn_3namBGewQe';
    }

    public static function slackBot(): string
    {
        return 'xox'.'b-'.'1234567890-abcdefghijklmnopqrstuv';
    }

    public static function jwt(): string
    {
        $header = 'eyJ'.'hbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9';
        $payload = 'eyJ'.'zdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4ifQ';

        return $header.'.'.$payload.'.signaturetokenxx';
    }

    public static function jwtHeader(): string
    {
        return 'eyJ'.'hbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9';
    }

    public static function rsaPrivateKey(): string
    {
        $begin = '-----BEGIN RSA '.'PRIVATE KEY-----';
        $end = '-----END RSA '.'PRIVATE KEY-----';

        return $begin."\nMIIEowIBAAKCAQEA0Z3CC0w=\n".$end;
    }
}
