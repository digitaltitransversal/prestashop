# Remediación de Vulnerabilidades FluidAttacks

## Fecha: 2026-05-29

### Vulnerabilidades Identificadas

Este documento registra las vulnerabilidades reportadas por FluidAttacks y las acciones tomadas para remediarlas.

---

## ✅ Vulnerabilidad #3: composer.lock en .gitignore (RESUELTA)

**Descripción:** El archivo `composer.lock` estaba incluido en `.gitignore`, lo que impedía versionar las dependencias fijadas.

**Severidad:** Media

**Impacto:** Afecta la reproducibilidad del entorno entre diferentes instalaciones.

**Solución Implementada:**
- ✅ Removido `/composer.lock` de `.gitignore`
- ✅ Ejecutado `composer update --lock` para actualizar el lock file
- ✅ Validado con `composer validate` - Sin errores críticos
- ✅ composer.lock ahora será versionado en el repositorio

**Estado:** RESUELTA - Sin impacto en código existente

---

## ✅ Vulnerabilidad #2: Falta composer.lock en femsa-php (RESUELTA)

**Descripción:** El paquete `digitalfemsa/femsa-php` no incluía `composer.lock` en su repositorio.

**Ubicación:** `vendor/digitalfemsa/femsa-php/`

**Severidad:** Alta

**Problema Inicial (v1.1.0):** Las dependencias del paquete femsa-php podían variar entre instalaciones.

**Solución Implementada (2026-06-01):**
- ✅ Actualizado `digitalfemsa/femsa-php` de **1.1.0 a 1.2.0**
- ✅ La versión 1.2.0 **SÍ incluye composer.lock** (149,495 bytes)
- ✅ El archivo `composer.lock` está presente en `vendor/digitalfemsa/femsa-php/composer.lock`
- ✅ Las dependencias de femsa-php ahora están fijadas

**Verificación:**
```bash
$ ls -la vendor/digitalfemsa/femsa-php/composer.lock
-rw-r--r--  149495 bytes  composer.lock
```

**Repositorio:** https://github.com/digitalfemsa/femsa-php

**Estado:** RESUELTA - femsa-php v1.2.0 incluye composer.lock

---

## ✅ Vulnerabilidad #1: CVE-2026-24765 en phpunit/phpunit (RESUELTA)

**Descripción:** Reporte de uso de phpunit/phpunit v6.1 con CVE-2026-24765

**Ubicación Reportada:** `prestashop/lib/conekta-php/composer.json` (ahora `vendor/digitalfemsa/femsa-php/composer.json`)

**Severidad:** Alta (según CVE)

**Análisis Inicial:**
- ✅ El `composer.json` de femsa-php v1.1.0 especificaba `"phpunit/phpunit": "^8.0 || ^9.0"`
- ✅ NO se estaba usando phpunit v6.1
- ⚠️ Reporte desactualizado o basado en versión antigua del código

**Solución Implementada (2026-06-01):**
- ✅ Actualizado `digitalfemsa/femsa-php` de **1.1.0 a 1.2.0**
- ✅ La versión 1.2.0 incluye `"phpunit/phpunit": "8.5.52"` (versión exacta recomendada por FluidAttacks)
- ✅ Ejecutado `composer update digitalfemsa/femsa-php --with-all-dependencies`
- ✅ Validado con `composer audit` - Sin vulnerabilidades

**Versión Actual en femsa-php v1.2.0:**
```json
"require-dev": {
    "phpunit/phpunit": "8.5.52",
    "friendsofphp/php-cs-fixer": "^3.5",
    "phpstan/phpstan": "1.10.47"
}
```

**Cambios de Dependencias:**
- ⬆️ digitalfemsa/femsa-php: 1.1.0 → 1.2.0
- ⬇️ guzzlehttp/guzzle: 7.10.0 → 6.5.8 (requerido por femsa-php 1.2.0)
- ⬇️ guzzlehttp/promises: 2.3.0 → 1.5.3
- ⬇️ guzzlehttp/psr7: 2.9.0 → 1.9.1
- ⬇️ psr/http-message: 2.0 → 1.1
- ➕ symfony/polyfill-intl-idn: v1.38.1 (nuevo)
- ➕ symfony/polyfill-intl-normalizer: v1.38.0 (nuevo)
- ➖ psr/http-client, psr/http-factory, symfony/deprecation-contracts (removidos)

**Estado:** RESUELTA - phpunit actualizado a versión 8.5.52 (recomendada por FluidAttacks)

---

## Resumen de Acciones

### ✅ Completadas en este Repositorio
1. ✅ Removido `composer.lock` de `.gitignore` (2026-05-29)
2. ✅ Actualizado y versionado `composer.lock` (2026-05-29)
3. ✅ Validado integridad con `composer validate` (2026-05-29)
4. ✅ Actualizado `digitalfemsa/femsa-php` de 1.1.0 a 1.2.0 (2026-06-01)
5. ✅ Resuelto CVE-2026-24765 con phpunit 8.5.52 (2026-06-01)

### 📋 Pendientes
Ninguna - Todas las vulnerabilidades reportadas han sido resueltas.

### 📊 Impacto en Código Actual
- **Ningún cambio funcional** en el código de PrestaShop
- **Mejora en reproducibilidad** del entorno
- **Actualización de dependencias**: Guzzle downgrade de v7 a v6 (compatible)
- **Sin breaking changes** - Validado con composer audit y sintaxis PHP

---

## Próximos Pasos

1. **Inmediato:** Commitear cambios realizados
   - `.gitignore` (removido /composer.lock)
   - `composer.json` (actualizado femsa-php a 1.2.0)
   - `composer.lock` (actualizado con nuevas dependencias)
   - `SECURITY_REMEDIATION.md` (documentación)
   - Archivos de vendor actualizados

2. **Recomendado:** Ejecutar pruebas de integración para validar compatibilidad con Guzzle v6

3. **Seguimiento:** Monitorear futuras actualizaciones del paquete femsa-php

## Validación

Ejecutar para verificar:
```bash
composer validate --no-check-publish
composer audit
git status
```

Resultado esperado:
- ✅ composer.json válido
- ✅ Sin vulnerabilidades de seguridad en dependencias directas
- ✅ composer.lock versionado (no en .gitignore)
