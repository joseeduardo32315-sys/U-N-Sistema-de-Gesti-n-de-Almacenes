import { chromium } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const OUTPUT_DIR = path.resolve('/home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/capturas_sistema_sacop');
const BASE_URL = 'http://127.0.0.1:5173';
const API_URL = 'http://127.0.0.1:8000/api/v1';

async function getAuthToken() {
  const response = await fetch(`${API_URL}/auth/login`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({
      login: 'admin',
      password: 'admin123',
    }),
  });
  const data = await response.json();
  return data.access_token;
}

async function main() {
  if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  } else {
    // Clean old png files
    const files = fs.readdirSync(OUTPUT_DIR);
    for (const file of files) {
      if (file.endsWith('.png')) {
        fs.unlinkSync(path.join(OUTPUT_DIR, file));
      }
    }
  }

  console.log('Obteniendo token de autenticación...');
  const token = await getAuthToken();
  console.log('Token obtenido:', token ? 'OK' : 'FALLÓ');

  const browser = await chromium.launch({
    headless: true,
  });

  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 2,
    locale: 'es-ES',
  });

  const page = await context.newPage();

  // Inyectar token en localStorage
  await page.addInitScript((authToken) => {
    localStorage.setItem('uyn_sacop_auth_token', authToken);
  }, token);

  const capture = async (name, waitMs = 500) => {
    await page.waitForTimeout(waitMs);
    const filePath = path.join(OUTPUT_DIR, `${name}.png`);
    await page.screenshot({ path: filePath, fullPage: false });
    console.log(`[CAPTURA] -> ${name}.png`);
  };

  const captureFull = async (name, waitMs = 500) => {
    await page.waitForTimeout(waitMs);
    const filePath = path.join(OUTPUT_DIR, `${name}.png`);
    await page.screenshot({ path: filePath, fullPage: true });
    console.log(`[CAPTURA FULL] -> ${name}.png`);
  };

  const closeModal = async () => {
    const closeBtn = page.locator('button[aria-label*="Cerrar"], button:has-text("Cancelar"), button:has-text("Cerrar")').first();
    if (await closeBtn.isVisible().catch(() => false)) {
      await closeBtn.click().catch(() => {});
      await page.waitForTimeout(400);
    } else {
      await page.keyboard.press('Escape');
      await page.waitForTimeout(400);
    }
  };

  try {
    // ----------------------------------------------------
    // 1. LOGIN & ERRORES
    // ----------------------------------------------------
    console.log('\n--- 1. Login & Vistas Especiales ---');
    const loginContext = await browser.newContext({
      viewport: { width: 1440, height: 900 },
      deviceScaleFactor: 2,
      locale: 'es-ES',
    });
    const loginPage = await loginContext.newPage();
    await loginPage.goto(`${BASE_URL}/login`);
    await loginPage.waitForLoadState('networkidle');
    await loginPage.waitForTimeout(500);
    await loginPage.screenshot({ path: path.join(OUTPUT_DIR, '01_login.png') });
    console.log('[CAPTURA] -> 01_login.png');
    await loginContext.close();

    // 2. ERROR 403
    await page.goto(`${BASE_URL}/sin-permiso`);
    await page.waitForLoadState('networkidle');
    await capture('02_error_403_acceso_restringido');

    // 3. ERROR 404
    await page.goto(`${BASE_URL}/ruta-inexistente-404`);
    await page.waitForLoadState('networkidle');
    await capture('03_error_404_no_encontrado');

    // ----------------------------------------------------
    // 2. DASHBOARD
    // ----------------------------------------------------
    console.log('\n--- 2. Dashboard ---');
    await page.goto(`${BASE_URL}/`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(800);
    await captureFull('04_dashboard_principal');

    // ----------------------------------------------------
    // 3. USUARIOS
    // ----------------------------------------------------
    console.log('\n--- 3. Módulo de Usuarios ---');
    await page.goto(`${BASE_URL}/usuarios`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(600);
    await capture('05_usuarios_lista');

    // Modal Crear Usuario
    try {
      const btnCreateUser = page.locator('button:has-text("Nuevo usuario"), button:has-text("Registrar usuario")').first();
      if (await btnCreateUser.isVisible()) {
        await btnCreateUser.click();
        await page.waitForTimeout(500);
        await capture('22_modal_usuario_nuevo');
        await closeModal();
      }
    } catch (e) {
      console.warn('Error modal usuario:', e.message);
    }

    // ----------------------------------------------------
    // 4. ROLES Y PERMISOS
    // ----------------------------------------------------
    console.log('\n--- 4. Módulo de Roles y Permisos ---');
    await page.goto(`${BASE_URL}/roles`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(600);
    await captureFull('06_roles_y_permisos');

    // ----------------------------------------------------
    // 5. EMPLEADOS
    // ----------------------------------------------------
    console.log('\n--- 5. Módulo de Empleados ---');
    await page.goto(`${BASE_URL}/empleados`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(600);
    await capture('07_empleados_lista');

    // Modal Crear Empleado
    try {
      const btnCreateEmployee = page.locator('button:has-text("Nuevo empleado"), button:has-text("Registrar empleado")').first();
      if (await btnCreateEmployee.isVisible()) {
        await btnCreateEmployee.click();
        await page.waitForTimeout(500);
        await capture('23_modal_empleado_nuevo');
        await closeModal();
      }
    } catch (e) {
      console.warn('Error modal empleado:', e.message);
    }

    // ----------------------------------------------------
    // 6. MODELOS DE PRENDA
    // ----------------------------------------------------
    console.log('\n--- 6. Módulo de Modelos de Prenda ---');
    await page.goto(`${BASE_URL}/modelos`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(600);
    await capture('08_modelos_prenda_lista');

    // Modal Crear Modelo
    try {
      const btnCreateModel = page.locator('button:has-text("Nuevo modelo"), button:has-text("Registrar modelo")').first();
      if (await btnCreateModel.isVisible()) {
        await btnCreateModel.click();
        await page.waitForTimeout(500);
        await capture('24_modal_modelo_prenda_nuevo');
        await closeModal();
      }
    } catch (e) {
      console.warn('Error modal modelo:', e.message);
    }

    // ----------------------------------------------------
    // 7. ÓRDENES DE PRODUCCIÓN
    // ----------------------------------------------------
    console.log('\n--- 7. Módulo de Órdenes de Producción ---');
    await page.goto(`${BASE_URL}/produccion/ordenes`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(600);
    await capture('09_ordenes_produccion_lista');

    // Modal Crear Orden
    try {
      const btnCreateOrder = page.locator('button:has-text("Registrar orden"), button:has-text("Nueva orden")').first();
      if (await btnCreateOrder.isVisible()) {
        await btnCreateOrder.click();
        await page.waitForTimeout(500);
        await capture('25_modal_orden_produccion_nueva');
        await closeModal();
      }
    } catch (e) {
      console.warn('Error modal crear orden:', e.message);
    }

    // Modal Detalle Orden
    try {
      await page.goto(`${BASE_URL}/produccion/ordenes`);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
      const btnDetailOrder = page.locator('button[title="Ver detalle"]').first();
      if (await btnDetailOrder.isVisible()) {
        await btnDetailOrder.click();
        await page.waitForTimeout(600);
        await capture('26_modal_orden_produccion_detalle');
        await closeModal();
      }
    } catch (e) {
      console.warn('Error modal detalle orden:', e.message);
    }

    // ----------------------------------------------------
    // 8. CORTES DE PRODUCCIÓN
    // ----------------------------------------------------
    console.log('\n--- 8. Módulo de Cortes de Producción ---');
    await page.goto(`${BASE_URL}/produccion/cortes`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(600);
    await capture('10_cortes_produccion_lista');

    // Modal Crear Corte
    try {
      const btnCreateCut = page.locator('button:has-text("Registrar corte"), button:has-text("Nuevo corte")').first();
      if (await btnCreateCut.isVisible()) {
        await btnCreateCut.click();
        await page.waitForTimeout(500);
        await capture('27_modal_corte_produccion_nuevo');
        await closeModal();
      }
    } catch (e) {
      console.warn('Error modal crear corte:', e.message);
    }

    // Modal Detalle Corte
    try {
      await page.goto(`${BASE_URL}/produccion/cortes`);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
      const btnDetailCut = page.locator('button:has-text("Ver detalle"), button[title="Ver detalle"]').first();
      if (await btnDetailCut.isVisible()) {
        await btnDetailCut.click();
        await page.waitForTimeout(600);
        await capture('28_modal_corte_produccion_detalle');
        await closeModal();
      }
    } catch (e) {
      console.warn('Error modal detalle corte:', e.message);
    }

    // Modal Clasificación Corte
    try {
      await page.goto(`${BASE_URL}/produccion/cortes`);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
      const btnClassifyCut = page.locator('button:has-text("Clasificación")').first();
      if (await btnClassifyCut.isVisible() && await btnClassifyCut.isEnabled()) {
        await btnClassifyCut.click();
        await page.waitForTimeout(600);
        await capture('29_modal_corte_clasificacion');
        await closeModal();
      }
    } catch (e) {
      console.warn('Error modal clasificar corte:', e.message);
    }

    // ----------------------------------------------------
    // 9. MOVIMIENTOS Y AVANCES
    // ----------------------------------------------------
    console.log('\n--- 9. Módulo de Movimientos y Avances ---');
    await page.goto(`${BASE_URL}/produccion/movimientos`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(600);
    await capture('11_movimientos_taller_lista');

    // Modal Despachar Movimiento
    try {
      const btnCreateMovement = page.locator('button:has-text("Registrar despacho"), button:has-text("Registrar movimiento")').first();
      if (await btnCreateMovement.isVisible()) {
        await btnCreateMovement.click();
        await page.waitForTimeout(500);
        await capture('30_modal_movimiento_despachar');
        await closeModal();
      }
    } catch (e) {
      console.warn('Error modal despachar movimiento:', e.message);
    }

    // Modal Detalle Movimiento
    try {
      await page.goto(`${BASE_URL}/produccion/movimientos`);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
      const btnDetailMovement = page.locator('button[title="Ver detalle"]').first();
      if (await btnDetailMovement.isVisible()) {
        await btnDetailMovement.click();
        await page.waitForTimeout(600);
        await capture('31_modal_movimiento_detalle');
        await closeModal();
      }
    } catch (e) {
      console.warn('Error modal detalle movimiento:', e.message);
    }

    // Modal Asignación de Operaciones / Avances
    try {
      await page.goto(`${BASE_URL}/produccion/movimientos`);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
      const bordadoTab = page.locator('nav[aria-label="Departamentos de producción"] button:has-text("Bordado")').first();
      if (await bordadoTab.isVisible()) {
        await bordadoTab.click();
        await page.waitForTimeout(600);
      }
      const btnOpsMovement = page.locator('button[title="Trabajadores y avances"]').first();
      if (await btnOpsMovement.isVisible()) {
        await btnOpsMovement.click();
        await page.waitForTimeout(600);
        await capture('32_modal_operaciones_asignacion');
        
        // Progress dialog inside
        const btnProgress = page.locator('button:has-text("Registrar avance"), button:has-text("Iniciar")').first();
        if (await btnProgress.isVisible() && await btnProgress.isEnabled()) {
          await btnProgress.click();
          await page.waitForTimeout(500);
          await capture('33_modal_operaciones_avance');
          await closeModal();
        }

        await closeModal();
      }
    } catch (e) {
      console.warn('Error modal operaciones:', e.message);
    }

    // ----------------------------------------------------
    // 10. INCIDENCIAS DE PRODUCCIÓN
    // ----------------------------------------------------
    console.log('\n--- 10. Módulo de Incidencias ---');
    await page.goto(`${BASE_URL}/incidencias`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(600);
    await capture('12_incidencias_lista');

    // Modal Reportar Incidencia
    try {
      const btnCreateIncident = page.locator('button:has-text("Registrar incidencia"), button:has-text("Reportar incidencia")').first();
      if (await btnCreateIncident.isVisible()) {
        await btnCreateIncident.click();
        await page.waitForTimeout(500);
        await capture('34_modal_incidencia_reportar');
        await closeModal();
      }
    } catch (e) {
      console.warn('Error modal reportar incidencia:', e.message);
    }

    // Modal Detalle Incidencia
    try {
      await page.goto(`${BASE_URL}/incidencias`);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
      const btnDetailIncident = page.locator('button:has-text("Ver detalle")').first();
      if (await btnDetailIncident.isVisible()) {
        await btnDetailIncident.click();
        await page.waitForTimeout(600);
        await capture('35_modal_incidencia_detalle');
        await closeModal();
      }
    } catch (e) {
      console.warn('Error modal detalle incidencia:', e.message);
    }

    // Modal Resolver Incidencia
    try {
      await page.goto(`${BASE_URL}/incidencias`);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
      const btnResolveIncident = page.locator('button:has-text("Resolver")').first();
      if (await btnResolveIncident.isVisible()) {
        await btnResolveIncident.click();
        await page.waitForTimeout(600);
        await capture('36_modal_incidencia_resolver');
        await closeModal();
      }
    } catch (e) {
      console.warn('Error modal resolver incidencia:', e.message);
    }

    // Modal Reproceso Incidencia
    try {
      await page.goto(`${BASE_URL}/incidencias`);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
      const btnReworkIncident = page.locator('button:has-text("Generar reproceso")').first();
      if (await btnReworkIncident.isVisible()) {
        await btnReworkIncident.click();
        await page.waitForTimeout(600);
        await capture('37_modal_incidencia_reproceso');
        await closeModal();
      }
    } catch (e) {
      console.warn('Error modal reproceso:', e.message);
    }

    // ----------------------------------------------------
    // 11. NÓMINA Y TARIFAS
    // ----------------------------------------------------
    console.log('\n--- 11. Módulo de Nómina ---');
    await page.goto(`${BASE_URL}/nomina`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(600);

    // Tab 1: Compensaciones
    try {
      const tabComp = page.locator('button:has-text("Esquemas de pago")').first();
      if (await tabComp.isVisible()) {
        await tabComp.click();
        await page.waitForTimeout(600);
        await capture('13_nomina_compensaciones');

        const btnCreateComp = page.locator('button:has-text("Registrar esquema")').first();
        if (await btnCreateComp.isVisible()) {
          await btnCreateComp.click();
          await page.waitForTimeout(500);
          await capture('38_modal_nomina_esquema_nuevo');
          await closeModal();
        }
      }
    } catch (e) {
      console.warn('Error nómina compensaciones:', e.message);
    }

    // Tab 2: Tarifas de Destajo
    try {
      await page.goto(`${BASE_URL}/nomina`);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
      const tabRates = page.locator('button:has-text("Tarifas de destajo")').first();
      if (await tabRates.isVisible()) {
        await tabRates.click();
        await page.waitForTimeout(600);
        await capture('14_nomina_tarifas_destajo');

        const btnCreateRate = page.locator('button:has-text("Registrar tarifa")').first();
        if (await btnCreateRate.isVisible()) {
          await btnCreateRate.click();
          await page.waitForTimeout(500);
          await capture('39_modal_nomina_tarifa_destajo_nueva');
          await closeModal();
        }
      }
    } catch (e) {
      console.warn('Error nómina tarifas:', e.message);
    }

    // Tab 3: Bordado
    try {
      await page.goto(`${BASE_URL}/nomina`);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
      const tabEmbroidery = page.locator('button:has-text("Fórmula de Bordado")').first();
      if (await tabEmbroidery.isVisible()) {
        await tabEmbroidery.click();
        await page.waitForTimeout(600);
        await capture('15_nomina_formula_bordado');

        const btnCreateEmb = page.locator('button:has-text("Registrar configuración")').first();
        if (await btnCreateEmb.isVisible()) {
          await btnCreateEmb.click();
          await page.waitForTimeout(500);
          await capture('40_modal_nomina_tarifa_bordado_nueva');
          await closeModal();
        }
      }
    } catch (e) {
      console.warn('Error nómina bordado:', e.message);
    }

    // Tab 4: Periodos de Nómina
    try {
      await page.goto(`${BASE_URL}/nomina`);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
      const tabPeriods = page.locator('button:has-text("Periodos de Nómina")').first();
      if (await tabPeriods.isVisible()) {
        await tabPeriods.click();
        await page.waitForTimeout(600);
        await capture('16_nomina_periodos_pago');

        const btnCreatePeriod = page.locator('button:has-text("Nuevo periodo")').first();
        if (await btnCreatePeriod.isVisible()) {
          await btnCreatePeriod.click();
          await page.waitForTimeout(500);
          await capture('41_modal_nomina_periodo_nuevo');
          await closeModal();
        }

        // Detalle Periodo (Sábana de pagos)
        await page.goto(`${BASE_URL}/nomina`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(500);
        const tabPeriodsRefresh = page.locator('button:has-text("Periodos de Nómina")').first();
        if (await tabPeriodsRefresh.isVisible()) {
          await tabPeriodsRefresh.click();
          await page.waitForTimeout(600);
        }
        const btnDetailPeriod = page.locator('button:has-text("Ver sábana de pagos")').first();
        if (await btnDetailPeriod.isVisible()) {
          await btnDetailPeriod.click();
          await page.waitForTimeout(700);
          await capture('42_modal_nomina_periodo_detalle');
          await closeModal();
        }
      }
    } catch (e) {
      console.warn('Error nómina periodos:', e.message);
    }

    // ----------------------------------------------------
    // 12. REPORTES
    // ----------------------------------------------------
    console.log('\n--- 12. Módulo de Reportes ---');
    await page.goto(`${BASE_URL}/reportes`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(600);

    // Tab Cortes
    try {
      const tabRepCuts = page.locator('button:has-text("Rendimiento de Cortes")').first();
      if (await tabRepCuts.isVisible()) {
        await tabRepCuts.click();
        await page.waitForTimeout(600);
        await capture('17_reportes_rendimiento_cortes');
      }
    } catch (e) {}

    // Tab Procesos
    try {
      const tabRepProc = page.locator('button:has-text("Eficiencia de Procesos")').first();
      if (await tabRepProc.isVisible()) {
        await tabRepProc.click();
        await page.waitForTimeout(600);
        await capture('18_reportes_eficiencia_procesos');
      }
    } catch (e) {}

    // Tab Mermas
    try {
      const tabRepLosses = page.locator('button:has-text("Mermas y Pérdidas")').first();
      if (await tabRepLosses.isVisible()) {
        await tabRepLosses.click();
        await page.waitForTimeout(600);
        await capture('19_reportes_mermas_perdidas');
      }
    } catch (e) {}

    // Tab Reprocesos
    try {
      const tabRepReworks = page.locator('button:has-text("Control de Reprocesos")').first();
      if (await tabRepReworks.isVisible()) {
        await tabRepReworks.click();
        await page.waitForTimeout(600);
        await capture('20_reportes_control_reprocesos');
      }
    } catch (e) {}

    // ----------------------------------------------------
    // 13. BITÁCORA
    // ----------------------------------------------------
    console.log('\n--- 13. Módulo de Bitácora ---');
    await page.goto(`${BASE_URL}/bitacora`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(600);
    await capture('21_bitacora_operaciones');

    // Modal Detalle Bitácora
    try {
      const btnDetailLog = page.locator('button[title="Ver detalle"]').first();
      if (await btnDetailLog.isVisible()) {
        await btnDetailLog.click();
        await page.waitForTimeout(600);
        await capture('43_modal_bitacora_detalle');
        await closeModal();
      }
    } catch (e) {
      console.warn('Error modal bitácora:', e.message);
    }

    console.log('\n¡PROCESO FINALIZADO CON ÉXITO! Total capturas tomadas.');
  } catch (error) {
    console.error('Error durante la captura general:', error);
  } finally {
    await browser.close();
  }
}

main();
