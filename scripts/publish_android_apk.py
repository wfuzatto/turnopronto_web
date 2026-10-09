#!/usr/bin/env python3
"""Synchronize verified TurnoPronto Android DEV APKs to website FTP."""
import ftplib
import hashlib
import io
import json
import os
import pathlib
import posixpath
import sys
import tempfile
import time
import urllib.request
import zipfile

API = "https://api.github.com/repos/wfuzatto/turnopronto_app/releases/tags/dev-latest"
ASSETS = {"TurnoPronto-dev.apk": "TurnoPronto.apk", "TurnoPronto-dev-arm64.apk": "TurnoPronto-arm64.apk"}

def digest_file(path):
    value = hashlib.sha256()
    with open(path, "rb") as inp:
        while block := inp.read(1024 * 1024):
            value.update(block)
    return value.hexdigest()

def fetch_apks(folder):
    req = urllib.request.Request(API, headers={"Accept": "application/vnd.github+json", "User-Agent": "TurnoPronto-website-publisher"})
    with urllib.request.urlopen(req, timeout=45) as response:
        release = json.load(response)
    assets = {entry["name"]: entry for entry in release.get("assets", [])}
    output = {}
    for source, name in ASSETS.items():
        if source not in assets:
            raise RuntimeError("Release asset missing: " + source)
        info = assets[source]
        expected_size = int(info["size"])
        expected_digest = str(info.get("digest") or "")
        if not (5_000_000 < expected_size < 200_000_000) or not expected_digest.startswith("sha256:"):
            raise RuntimeError("Invalid release metadata for " + source)
        url = info["browser_download_url"]
        if not url.startswith("https://github.com/wfuzatto/turnopronto_app/releases/download/"):
            raise RuntimeError("Untrusted release download URL")
        target = folder / name
        for attempt in range(3):
            try:
                req = urllib.request.Request(url, headers={"User-Agent": "TurnoPronto-website-publisher"})
                with urllib.request.urlopen(req, timeout=180) as response, target.open("wb") as out:
                    while block := response.read(1024 * 1024):
                        out.write(block)
                break
            except OSError:
                target.unlink(missing_ok=True)
                if attempt == 2:
                    raise
                time.sleep((attempt + 1) * 3)
        checksum = digest_file(target)
        if target.stat().st_size != expected_size or checksum != expected_digest[7:]:
            raise RuntimeError("GitHub size or SHA256 verification failed: " + source)
        with zipfile.ZipFile(target) as apk:
            names = set(apk.namelist())
            if not {"AndroidManifest.xml", "classes.dex"} <= names:
                raise RuntimeError("Invalid APK archive structure: " + source)
        output[name] = (target, target.stat().st_size, checksum)
        print("Verified GitHub APK:", source, expected_size, checksum, flush=True)
    return output

def ftp_connect():
    kind = (os.environ.get("FTP_PROTOCOL") or "ftp").lower()
    if kind in ("ftps", "ftpes", "ftp-tls"):
        ftp = ftplib.FTP_TLS(timeout=90)
    elif kind == "ftp":
        ftp = ftplib.FTP(timeout=90)
    else:
        raise RuntimeError("Unsupported FTP_PROTOCOL")
    ftp.connect(os.environ["FTP_SERVER"], int(os.environ.get("FTP_PORT") or "21"))
    ftp.login(os.environ["FTP_USERNAME"], os.environ["FTP_PASSWORD"])
    if isinstance(ftp, ftplib.FTP_TLS):
        ftp.prot_p()
    ftp.voidcmd("TYPE I")
    return ftp

def mkdirs(ftp, path):
    prefix = ""
    for segment in path.split("/"):
        if segment:
            prefix += "/" + segment
            try:
                ftp.mkd(prefix)
            except ftplib.error_perm as exc:
                if not str(exc).startswith("550"):
                    raise
                current = ftp.pwd()
                try:
                    ftp.cwd(prefix)
                finally:
                    ftp.cwd(current)

def size_or_none(ftp, path):
    try:
        return ftp.size(path)
    except ftplib.error_perm as exc:
        if str(exc).startswith("550"):
            return None
        raise

def ftp_sha256(ftp, path):
    value = hashlib.sha256()
    ftp.retrbinary("RETR " + path, value.update, blocksize=1024 * 1024)
    return value.hexdigest()

def upload(ftp, remote, data, expected_sha, size, backups, stamp):
    existing_size = size_or_none(ftp, remote)
    if existing_size == size and ftp_sha256(ftp, remote) == expected_sha:
        print("Already current on FTP:", remote, flush=True)
        return
    temp = remote + ".new"
    if size_or_none(ftp, temp) is not None:
        ftp.delete(temp)
    with (open(data, "rb") if isinstance(data, pathlib.Path) else io.BytesIO(data)) as file:
        ftp.storbinary("STOR " + temp, file, blocksize=1024 * 1024)
    if size_or_none(ftp, temp) != size or ftp_sha256(ftp, temp) != expected_sha:
        ftp.delete(temp)
        raise RuntimeError("FTP upload integrity mismatch: " + remote)
    old = None
    if existing_size is not None:
        old = posixpath.join(backups, posixpath.basename(remote) + "." + stamp + ".bak")
        ftp.rename(remote, old)
    try:
        ftp.rename(temp, remote)
    except Exception:
        if old is not None:
            ftp.rename(old, remote)
        raise
    if size_or_none(ftp, remote) != size:
        raise RuntimeError("FTP published file size mismatch: " + remote)
    print("Published:", remote, "size", size, flush=True)

def index_html():
    return """<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Baixar TurnoPronto Android</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#eef6ff;color:#102244;font:16px system-ui}
main{max-width:500px;margin:16px;padding:28px;background:white;border-radius:22px;box-shadow:0 12px 40px #1235}
h1{margin-top:0}.green{color:#06ad7b}p{line-height:1.5;color:#567}.note{background:#fff6e7;padding:12px;border-radius:10px}
a{display:block;background:#0866ff;color:white;text-align:center;text-decoration:none;font-weight:750;border-radius:12px;padding:16px;margin:12px 0}
a.secondary{background:#eaf3ff;color:#0866ff}</style></head>
<body><main><h1>Turno<span class="green">Pronto</span></h1>
<p>Download do aplicativo Android.</p>
<p class="note"><strong>Versão de desenvolvimento (DEV).</strong> Destinada a testes, instalada separadamente do aplicativo de produção. Não é a versão estável assinada.</p>
<a href="TurnoPronto-arm64.apk">Baixar para Android ARM64 (recomendado)</a>
<a href="TurnoPronto.apk" class="secondary">Baixar APK universal</a>
<p>Os arquivos são sincronizados com a última versão de desenvolvimento disponível no GitHub.</p>
</main></body></html>""".encode()

def main():
    root = (os.environ.get("FTP_PATH") or "/public_html/").rstrip("/")
    if not root.startswith("/") or root == "/":
        raise RuntimeError("Unsafe FTP_PATH")
    dest = posixpath.join(root, "download")
    backup_dir = posixpath.join(root, "storage", "backups", "android")
    stamp = time.strftime("%Y%m%d_%H%M%S", time.gmtime())
    with tempfile.TemporaryDirectory() as work:
        files = fetch_apks(pathlib.Path(work))
        manifest = {"kind": "DEVELOPMENT", "release": "dev-latest", "assets": {
            name: {"size": size, "sha256": sha} for name, (_, size, sha) in files.items()
        }}
        checksums = "".join(sha + "  " + name + "\n" for name, (_, _, sha) in files.items()).encode()
        extra = {"TurnoPronto.sha256": checksums,
                 "release.json": (json.dumps(manifest, indent=2) + "\n").encode(),
                 "index.html": index_html()}
        ftp = ftp_connect()
        try:
            mkdirs(ftp, dest)
            mkdirs(ftp, backup_dir)
            for name, (local, size, sha) in files.items():
                upload(ftp, posixpath.join(dest, name), local, sha, size, backup_dir, stamp)
            for name, data in extra.items():
                upload(ftp, posixpath.join(dest, name), data,
                       hashlib.sha256(data).hexdigest(), len(data), backup_dir, stamp)
            print("FTP_SYNC_SUCCEEDED", flush=True)
        finally:
            try:
                ftp.quit()
            except Exception:
                ftp.close()

if __name__ == "__main__":
    main()
