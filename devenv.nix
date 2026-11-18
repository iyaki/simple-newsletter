{
  pkgs,
  lib,
  config,
  ...
}:

let
  /* PHP embebido en FrankenPHP (mismo runtime que la imagen devcontainer/prod):
     embed + zts. El override vacía el set default de extensiones de nixpkgs:
     declarar TODAS explícitamente (set probado en la migración de
     directorio-marcial, con el par pgsql cambiado por sqlite). */
  phpWorker = (pkgs.php85.override {
    embedSupport = true;
    ztsSupport = true;
  }).override {
    extensions = { all, enabled }: with all; [
      bcmath
      ctype
      curl
      dom
      fileinfo
      filter
      iconv
      intl
      mbstring
      openssl
      pcntl
      pdo
      pdo_sqlite
      posix
      readline
      session
      simplexml
      sockets
      sodium
      sqlite3
      tokenizer
      xmlreader
      xmlwriter
      xdebug
      zip
      zlib
    ];
    extraConfig =
      # mismos ini que monta la imagen Docker: única fuente de verdad
      builtins.readFile ./.php/php.ini
      + "\n"
      + builtins.readFile ./.php/development.ini
      + "\n"
      # php.ini trae la ruta de contenedor /app: la línea final gana
      + "auto_prepend_file = ${config.devenv.root}/libs/bootstrap.php\n";
  };

  frankenphpDev = pkgs.frankenphp.override { php = phpWorker; };

  /* El wrapper de nixpkgs corre composer con SU php (buildEnv base, sin pcov)
     y exporta su PHP_BINARY/PHP_INI_SCAN_DIR: los scripts de composer que
     piden cobertura (pest --coverage) quedarían sin driver. Correr el phar
     directo con el CLI buildEnv. */
  composerDev = pkgs.writeShellScriptBin "composer" ''
    exec ${config.languages.php.package}/bin/php ${pkgs.php85Packages.composer}/share/php/composer/bin/composer "$@"
  '';
in
{
  dotenv.disableHint = true;

  packages = with pkgs; [
    # CLI interactivo de frankenphp (validate / php-cli)
    frankenphpDev
    nixd
    git
    gh
    lefthook
    curl
  ];

  languages.php = {
    enable = true;
    /* CLI: pest, composer, scripts. Defaults de nixpkgs + lo que falta.
       pcov para cobertura (los XDEBUG_MODE=coverage de composer scripts son
       inertes sin Xdebug; pest usa pcov). */
    package = pkgs.php85.buildEnv {
      extensions = { all, enabled }: with all; enabled ++ [ intl pcov pdo_sqlite ];
      extraConfig = "error_reporting = E_ALL\n";
    };
    packages.composer = composerDev;
  };

  languages.javascript = {
    enable = true; # intelephense + tooling de package.json
    package = pkgs.nodejs;
    npm.enable = true;
    npm.install.enable = true;
  };

  # Sink de SMTP de dev: nada sale del host. UI http://127.0.0.1:8025
  services.mailpit.enable = true;

  tasks = {
    /* Primera entrada al shell: .env desde example.env + SECRET_KEY generado,
       vendor y hooks. Se salta mientras todo exista. */
    "sn:setup" = {
      exec = ''
        cd "$DEVENV_ROOT"
        [ -f .env ] || cp example.env .env
        grep -q '^SECRET_KEY=..*' .env || \
          sed -i "s/^SECRET_KEY=$/SECRET_KEY=$(php -r 'echo bin2hex(random_bytes(32));')/" .env
        composer install
        lefthook install
      '';
      status = "test -f .env && test -d vendor && grep -q '^SECRET_KEY=.\\+' .env";
      before = [ "devenv:enterShell" ];
    };

    /* Las migraciones NO son idempotentes (CREATE TABLE sin IF NOT EXISTS):
       crear la DB de dev sólo si no existe. Reset manual:
       rm data/database.sqlite3 && devenv tasks run sn:db-init */
    "sn:db-init" = {
      exec = ''
        cd "$DEVENV_ROOT"
        [ -f data/database.sqlite3 ] || php scripts/e2e-db-init.php data/database.sqlite3
      '';
      before = [ "devenv:enterShell" ];
    };
  };

  processes.frankenphp = {
    # alocación dinámica: devenv up y devenv test pueden convivir
    ports.main.allocate = 8080;
    env.CADDY_HTTP_PORT = builtins.toString config.processes.frankenphp.ports.main.value;
    exec = ''
      cd "$DEVENV_ROOT"
      set -a
      [ -f .env ] && . ./.env || true
      set +a
      # mailpit: pisa el relay que traiga el .env
      export SMTP_HOST=127.0.0.1
      export SMTP_PORT=1025
      export SMTP_ENCRYPTION=
      export SMTP_ALLOW_SELF_SIGNED=1
      export APP_ROOT="$DEVENV_ROOT"
      # anula un NEWSLETTER_DB_PATH=/app/... heredado del .env del devcontainer
      export NEWSLETTER_DB_PATH="$DEVENV_ROOT/data/database.sqlite3"
      export URI_SELF="http://localhost:$CADDY_HTTP_PORT"
      export SERVER_NAME="http://localhost:$CADDY_HTTP_PORT"
      # ruta absoluta al binario: inmune al PATH del manager (modo test)
      exec ${frankenphpDev}/bin/frankenphp run --config "$DEVENV_ROOT/.caddy/Caddyfile-dev" --adapter caddyfile
    '';
    after = [ "sn:db-init@succeeded" ];
    # reinicia al editar el Caddyfile (los .php/*.ini se hornean en eval:
    # requieren devenv processes down && devenv up)
    watch.paths = [ ./.caddy ];
    ready.exec =
      let
        port = builtins.toString config.processes.frankenphp.ports.main.value;
      in
      ''
        curl -fsS "http://127.0.0.1:${port}/" >/dev/null
      '';
  };

  env = {
    LANG = "es_AR.UTF-8";
    TZ = "America/Argentina/Buenos_Aires";
    LOCALE_ARCHIVE = lib.mkIf pkgs.stdenv.hostPlatform.isLinux "${pkgs.glibcLocales}/lib/locale/locale-archive";
  };

  enterShell = ''
    export PATH="$DEVENV_ROOT/vendor/bin:$DEVENV_ROOT/node_modules/.bin:$PATH"
    lefthook install >/dev/null 2>&1 || true
  '';
}
