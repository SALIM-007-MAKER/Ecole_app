@echo off
rem Compile les deux feuilles Tailwind (binaire standalone v3.4.17 requis : voir README.md). A lancer depuis n'importe ou.
cd /d "%~dp0\..\.."
if not exist "tools\tailwind\tailwindcss.exe" (
  echo Binaire absent : telechargez tailwindcss-windows-x64.exe v3.4.17 en tools\tailwind\tailwindcss.exe ^(voir tools\tailwind\README.md^)
  exit /b 2
)
set TW_PREFLIGHT=0
tools\tailwind\tailwindcss.exe -c tools/tailwind/tailwind.config.js -i tools/tailwind/input.css -o public/assets/vendor/tailwind/tailwind.css --minify || exit /b 1
set TW_PREFLIGHT=1
tools\tailwind\tailwindcss.exe -c tools/tailwind/tailwind.config.js -i tools/tailwind/input.css -o public/assets/vendor/tailwind/tailwind-preflight.css --minify || exit /b 1
echo OK
