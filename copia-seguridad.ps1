<#
    Copia de seguridad de Ludia: la base de datos y los archivos.

    Se guarda FUERA de htdocs a propósito. Una copia dentro del proyecto la
    serviría Apache, y cualquiera podría descargar la base entera escribiendo
    la dirección en el navegador.

    Uso:
        .\copia-seguridad.ps1                  copia normal
        .\copia-seguridad.ps1 -Motivo "antes-de-tocar-planes"
        .\copia-seguridad.ps1 -Listar          ver las copias que hay

    Para restaurar, lee RESTAURAR.md (se escribe dentro de cada copia).
#>

[CmdletBinding()]
param(
    [string] $Motivo = '',
    [string] $Destino = 'D:\copias-ludia',
    [int]    $Conservar = 30,
    [switch] $Listar
)

$ErrorActionPreference = 'Stop'

$proyecto = 'D:\xampp\htdocs\LUDIRA'
$mysqldump = 'D:\xampp\mysql\bin\mysqldump.exe'
$baseDatos = 'ludia'
$usuario   = 'root'

# ---------- Listar y salir ----------
if ($Listar) {
    if (-not (Test-Path $Destino)) {
        Write-Host "Todavia no hay ninguna copia en $Destino"
        return
    }
    Get-ChildItem $Destino -Directory | Sort-Object Name -Descending | ForEach-Object {
        $mb = [math]::Round(((Get-ChildItem $_.FullName -Recurse -File | Measure-Object Length -Sum).Sum / 1MB), 2)
        [pscustomobject]@{ Copia = $_.Name; MB = $mb; Fecha = $_.CreationTime }
    } | Format-Table -AutoSize
    return
}

# ---------- Comprobaciones antes de prometer nada ----------
if (-not (Test-Path $mysqldump)) { throw "No encuentro mysqldump en $mysqldump" }
if (-not (Test-Path $proyecto))  { throw "No encuentro el proyecto en $proyecto" }

$sello = Get-Date -Format 'yyyy-MM-dd_HHmm'
if ($Motivo -ne '') {
    $limpio = ($Motivo -replace '[^a-zA-Z0-9\-_]', '-')
    $sello = "$sello`_$limpio"
}
$carpeta = Join-Path $Destino $sello

if (Test-Path $carpeta) { throw "Ya existe una copia llamada $sello" }
New-Item -ItemType Directory -Path $carpeta -Force | Out-Null

Write-Host "Copia de seguridad -> $carpeta"

# ---------- 1. La base de datos ----------
# --routines y --events para que no se quede nada fuera; --single-transaction
# evita bloquear las tablas mientras alguien esta usando la aplicacion.
$archivoSql = Join-Path $carpeta 'ludia.sql'
Write-Host "  base de datos..." -NoNewline

& $mysqldump --user=$usuario --default-character-set=utf8mb4 `
    --routines --events --single-transaction `
    --result-file=$archivoSql $baseDatos

if ($LASTEXITCODE -ne 0) {
    # Si el volcado falla, la carpeta a medias es peor que no tener copia:
    # da falsa sensacion de respaldo. Se borra y se avisa.
    Remove-Item $carpeta -Recurse -Force
    throw "mysqldump fallo (codigo $LASTEXITCODE). No se creo la copia."
}

$pesoSql = [math]::Round((Get-Item $archivoSql).Length / 1KB, 1)
Write-Host " $pesoSql KB"

# ---------- 2. Los archivos ----------
# Se copia TODO el proyecto: con menos de 1 MB, elegir que llevarse solo
# introduce el riesgo de olvidar algo. Se excluye lo que se regenera.
Write-Host "  archivos..." -NoNewline
$archivos = Join-Path $carpeta 'proyecto'
New-Item -ItemType Directory -Path $archivos -Force | Out-Null

robocopy $proyecto $archivos /E /NFL /NDL /NJH /NJS /NP /XD '.git' 'node_modules' | Out-Null
# robocopy devuelve 0-7 como exito (1 = se copiaron archivos). 8 o mas es error.
if ($LASTEXITCODE -ge 8) {
    Remove-Item $carpeta -Recurse -Force
    throw "robocopy fallo (codigo $LASTEXITCODE). No se creo la copia."
}
$global:LASTEXITCODE = 0

$cuantos = (Get-ChildItem $archivos -Recurse -File).Count
Write-Host " $cuantos archivos"

# ---------- 3. Las instrucciones para restaurar ----------
# Van DENTRO de la copia: el dia que haga falta, quien la abra puede estar
# nervioso y no va a buscar la documentacion en otro sitio.
#
# Las comillas simples alrededor de 'a las' no sobran: en el formato de fechas
# de .NET la letra 's' son los SEGUNDOS, asi que sin ellas salia "a la41 13:09".
$instrucciones = @"
# Restaurar esta copia

Copia tomada el $(Get-Date -Format "dd/MM/yyyy 'a las' HH:mm").

## 1. La base de datos

Borra la base actual y vuelve a crearla vacia:

    D:\xampp\mysql\bin\mysql.exe -u root -e "DROP DATABASE IF EXISTS ludia; CREATE DATABASE ludia CHARACTER SET utf8mb4;"

Carga el volcado:

    D:\xampp\mysql\bin\mysql.exe -u root ludia < "$archivoSql"

## 2. Los archivos

Estan en la carpeta ``proyecto`` de al lado, tal cual estaban.
Copialos sobre D:\xampp\htdocs\LUDIRA.

**Ojo con config/config.php**: lleva las contrasenas. Si restauras en otra
maquina, revisa que los datos de conexion sean los de esa maquina.

## 3. Comprobar que quedo bien

Entra a http://localhost/LUDIRA/ y mira que la portada cargue los tres planes
con sus precios. Si los precios salen en cero, el volcado no se cargo.
"@

# -Encoding UTF8 de PowerShell 5.1 antepone un BOM, que se ve como un caracter
# raro delante del "# Restaurar esta copia". Se escribe sin el.
[System.IO.File]::WriteAllText(
    (Join-Path $carpeta 'RESTAURAR.md'),
    $instrucciones,
    (New-Object System.Text.UTF8Encoding $false))

# ---------- 4. Borrar las mas viejas ----------
# Solo las carpetas con forma de copia. Sin este filtro, cualquier otra cosa
# que alguien deje en el destino cuenta como copia y expulsa una de verdad al
# llegar al tope.
#
# La etiqueta del final es OPCIONAL: hay copias sueltas (2026-09-29_1310) y
# copias con nombre (2026-09-18_0714_antes-de-la-escuela). Si el patron no
# admite las dos, las etiquetadas no se cuentan ni se borran nunca y el
# recorte deja de funcionar sin avisar.
$todas = Get-ChildItem $Destino -Directory |
    Where-Object { $_.Name -match '^\d{4}-\d{2}-\d{2}_\d{4}(_.+)?$' } |
    Sort-Object Name -Descending
if ($todas.Count -gt $Conservar) {
    $sobran = $todas | Select-Object -Skip $Conservar
    foreach ($vieja in $sobran) {
        Remove-Item $vieja.FullName -Recurse -Force
        Write-Host "  se borro la copia antigua $($vieja.Name)"
    }
}

$pesoTotal = [math]::Round(((Get-ChildItem $carpeta -Recurse -File | Measure-Object Length -Sum).Sum / 1MB), 2)
Write-Host ""
Write-Host "Listo. $pesoTotal MB en $carpeta"
Write-Host "Hay $((Get-ChildItem $Destino -Directory).Count) copias guardadas (se conservan $Conservar)."
