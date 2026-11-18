# Imagen OCI de producción, derivada del MISMO pin de nixpkgs que devenv:
# devenv.lock es la única fuente de verdad. El runtime corre el frankenphp de
# nixpkgs con php85 embed+zts (igual que devenv.nix), sin xdebug y con los
# ini php + production horneados.
#
# El vendor NO se resuelve acá: composer install necesita red y el sandbox de
# Nix no la tiene. scripts/build-image.sh arma .image-staging (app + vendor
# --no-dev, el mismo set que COPYeaba el viejo Dockerfile) y después:
#
#   nix build -f infrastructure/image.nix
#
# El resultado es un tarball OCI (`docker load`). Sin HEALTHCHECK: el formato
# OCI no lo soporta (podman lo ignora con warning).
{ staging ? ../.image-staging }:

let
  # mismo nixpkgs que devenv (nodo "nixpkgs" = cachix/devenv-nixpkgs rolling)
  lock = builtins.fromJSON (builtins.readFile ../devenv.lock);
  nixpkgsLocked = lock.nodes.nixpkgs.locked;
  pkgs = import
    (builtins.fetchTarball
      "https://github.com/${nixpkgsLocked.owner}/${nixpkgsLocked.repo}/archive/${nixpkgsLocked.rev}.tar.gz")
    { };

  # Ini prod: mismos archivos que devenv hornea para dev (una sola fuente de
  # verdad), con production.ini en vez de development.ini. El auto_prepend_file
  # de php.ini ya apunta a /app: en el contenedor es la ruta correcta.
  phpIniProd =
    builtins.readFile ../.php/php.ini
    + "\n"
    + builtins.readFile ../.php/production.ini;

  # Espejo del phpWorker de devenv.nix (embed+zts, mismo set de extensiones)
  # sin xdebug. Derivación distinta a la de dev = compilación propia la
  # primera vez, cacheada después. (opcache no se declara: en php85 de
  # nixpkgs viene compilado adentro.)
  phpProd = (pkgs.php85.override {
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
      zip
      zlib
    ];
    extraConfig = phpIniProd;
  };

  frankenphpProd = pkgs.frankenphp.override { php = phpProd; };

  # La app en /app desde el staging que arma build-image.sh.
  app = pkgs.runCommand "simple-newsletter-app" { } ''
    mkdir -p $out/app
    cp -a ${staging}/. $out/app/
  '';

  caddyfile = pkgs.runCommand "simple-newsletter-caddyfile" { } ''
    mkdir -p $out/etc/frankenphp
    cp ${../.caddy/Caddyfile-prod} $out/etc/frankenphp/Caddyfile
  '';
in
pkgs.dockerTools.buildImage {
  name = "simple-newsletter";
  tag = "latest";
  copyToRoot = pkgs.buildEnv {
    name = "simple-newsletter-rootfs";
    paths = [
      app
      caddyfile
      frankenphpProd
      phpProd # CLI: docker exec ... php bin/send-newsletters.php (cron)
      pkgs.cacert
    ];
  };
  # Todo el rootfs viaja uid 1000 (= runtime del VPS rootful es root, así que
  # es cosmético; lo dejamos como DM).
  uid = 1000;
  gid = 1000;
  extraCommands = ''
    # /tmp con sticky: HOME del runtime apunta acá (caddy guarda su autosave
    # en ~/.config); los store paths viajan read-only, el chmod corre sobre
    # el layer mutable antes del tar.
    mkdir -p tmp
    chmod 1777 tmp
  '';
  config = {
    # sin User: en el VPS (podman rootful) el contenedor corre como root y
    # bindea :80/443 sin caps extra (el tar del layer de Nix no preserva
    # xattrs → file capabilities imposibles). En podman rootless local el
    # default es el uid del host: smoke con puertos >1024.
    WorkingDir = "/app";
    Env = [
      "LANG=C.UTF-8"
      "HOME=/tmp"
      # los binarios viven en /bin del buildEnv; el PATH default de Docker no
      # lo incluye y el cron necesita resolver `php`
      "PATH=/bin"
      # TLS saliente (SMTP STARTTLS, Sentry) contra el bundle de nixpkgs
      "SSL_CERT_FILE=${pkgs.cacert}/etc/ssl/certs/ca-bundle.crt"
      "CURL_CA_BUNDLE=${pkgs.cacert}/etc/ssl/certs/ca-bundle.crt"
    ];
    Entrypoint = [
      "/bin/frankenphp"
      "run"
      "--config"
      "/etc/frankenphp/Caddyfile"
      "--adapter"
      "caddyfile"
    ];
  };
}
