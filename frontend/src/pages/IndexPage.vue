<template>
  <div v-if="booting" class="boot"
    ><q-spinner size="40px" color="primary" /><p>Abriendo Tópico…</p></div
  >
  <div v-else-if="!currentUser" class="login-page">
    <div class="login-backdrop" aria-hidden="true">
      <img
        src="/fondo-salud-animado.svg"
        class="login-background-art"
        alt=""
        draggable="false"
      />
    </div>
    <div class="login-shell">
      <q-card flat class="login-card">
        <header class="login-brand">
          <img
            src="/logo-iestpss.jpg"
            width="72"
            height="72"
            alt="Logo del Instituto de Educación Superior Paucar del Sarasara"
          />
          <h1>Tópico IESTPSS</h1>
          <p>Instituto de Educación Superior<br />Paucar del Sarasara</p>
        </header>
        <section>
          <h2>Iniciar sesión</h2>
          <p class="muted login-subtitle">Ingresa tu DNI y contraseña.</p>
          <q-banner
            v-if="sessionNotice"
            rounded
            class="bg-teal-1 text-primary q-mb-md"
            role="status"
            >{{ sessionNotice }}</q-banner
          >
          <q-form @submit="login" class="q-gutter-y-md">
            <q-input
              outlined
              v-model="credentials.dni"
              type="text"
              inputmode="numeric"
              mask="########"
              maxlength="8"
              pattern="[0-9]{8}"
              autocomplete="username"
              label="DNI"
              :rules="[
                required,
                value => validDni(value) || 'El DNI debe contener ocho dígitos.'
              ]"
            />
            <q-input
              outlined
              v-model="credentials.password"
              autocomplete="current-password"
              :type="showPassword ? 'text' : 'password'"
              label="Contraseña"
              :rules="[required]"
            >
              <template #append
                ><q-btn
                  flat
                  round
                  dense
                  :icon="showPassword ? 'visibility_off' : 'visibility'"
                  :aria-label="
                    showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'
                  "
                  @click="showPassword = !showPassword"
              /></template>
            </q-input>
            <q-banner
              v-if="success"
              rounded
              class="bg-teal-1 text-primary"
              role="status"
              >{{ success }}</q-banner
            >
            <q-banner
              v-if="error"
              rounded
              class="bg-red-1 text-negative"
              role="alert"
              >{{ error }}</q-banner
            >
            <q-btn
              unelevated
              color="primary"
              label="Ingresar"
              type="submit"
              class="full-width login-submit"
              no-caps
              :loading="busy"
              :disable="!validDni(credentials.dni)"
            />
          </q-form>
          <p class="login-first-access"
            ><q-icon name="info_outline" size="17px" /> Primer ingreso: tu
            contraseña es tu DNI. Deberás cambiarla antes de acceder al
            sistema.</p
          >
          <p class="login-help">¿Necesitas ayuda? Contacta al coordinador.</p>
        </section>
      </q-card>
      <p class="login-footer"
        ><q-icon name="health_and_safety" size="16px" /> Bienestar y atención
        estudiantil</p
      >
    </div>
  </div>
  <q-layout v-else view="lHh Lpr lFf" class="app-shell">
    <q-drawer v-model="drawer" show-if-above :width="248" class="sidebar">
      <div class="brand sidebar-brand"
        ><img
          src="/logo-iestpss.jpg"
          alt="Logo IESTPSS"
          class="institution-logo"
        /><div
          >TÓPICO IESTPSS<small
            >Instituto de Educación Superior Paucar del Sarasara</small
          ></div
        ></div
      >
      <div class="nav-caption">ESPACIO DE TRABAJO</div
      ><q-list padding
        ><q-item
          v-for="item in navigation"
          :key="item.key"
          clickable
          :disable="
            currentUser.uses_initial_password && item.key !== 'settings'
          "
          :active="section === item.key"
          active-class="nav-active"
          @click="navigate(item.key)"
          ><q-item-section avatar><q-icon :name="item.icon" /></q-item-section
          ><q-item-section>{{ item.label }}</q-item-section
          ><q-item-section side v-if="item.key === 'requests' && stats?.pending"
            ><q-badge rounded color="orange-8">{{
              stats.pending
            }}</q-badge></q-item-section
          ></q-item
        ></q-list
      >
      <div class="sidebar-footer"
        ><q-icon name="health_and_safety" size="28px" /><strong
          >Atención con propósito</strong
        ><p>La salud de los estudiantes,<br />en el centro de todo.</p
        ><q-badge outline color="primary">Proceso de atención</q-badge></div
      >
    </q-drawer>
    <q-header class="topbar"
      ><q-toolbar
        ><q-btn
          flat
          round
          icon="menu"
          class="lt-md"
          @click="drawer = !drawer"
        /><div class="breadcrumb"
          >Tópico IESTPSS <q-icon name="chevron_right" />
          <strong>{{ pageTitle }}</strong></div
        ><q-space /><span class="date-label gt-sm">{{ today }}</span
        ><q-separator vertical inset class="q-mx-lg" /><q-avatar
          color="teal-1"
          text-color="primary"
          size="38px"
          >{{ initials }}</q-avatar
        ><div class="q-ml-sm account-name"
          ><strong>{{ currentUser.name }}</strong
          ><small>{{ roleLabels[currentUser.role] }}</small></div
        ><q-btn flat round icon="expand_more"
          ><q-menu
            ><q-list style="min-width: 190px"
              ><q-item clickable v-close-popup @click="navigate('settings')"
                ><q-item-section>Configuración</q-item-section></q-item
              ><q-item clickable v-close-popup @click="logout"
                ><q-item-section>Cerrar sesión</q-item-section></q-item
              ></q-list
            ></q-menu
          ></q-btn
        ></q-toolbar
      ></q-header
    >
    <q-page-container
      ><q-page class="workspace">
        <q-banner
          v-if="currentUser.uses_initial_password"
          rounded
          class="bg-teal-1 text-primary q-mb-lg"
          role="status"
        >
          Bienvenido al sistema. Para habilitar las demás funciones, cambia tu
          contraseña inicial desde Configuración.
        </q-banner>
        <q-banner
          v-if="error"
          rounded
          class="bg-red-1 text-negative q-mb-lg"
          role="alert"
          >{{ error
          }}<template #action
            ><q-btn flat icon="close" @click="error = ''" /></template
        ></q-banner>
        <q-banner
          v-if="success"
          rounded
          class="bg-teal-1 text-primary q-mb-lg"
          role="status"
          >{{ success
          }}<template #action
            ><q-btn flat icon="close" @click="success = ''" /></template
        ></q-banner>
        <div class="page-heading"
          ><div
            ><div class="eyebrow">{{
              section === "dashboard"
                ? "TU CENTRO DE OPERACIONES"
                : "GESTIÓN DEL TÓPICO"
            }}</div
            ><h1>{{ pageTitle }}</h1
            ><p class="muted">{{ subtitles[section] }}</p>
            <small
              v-if="section !== 'settings'"
              :class="syncError ? 'text-negative' : 'muted'"
              role="status"
              >{{
                syncError ||
                (lastSync
                  ? "Actualización automática cada 5 s · " + lastSync
                  : "Sincronizando…")
              }}</small
            ></div
          ><div class="row q-gutter-sm"
            ><q-btn
              flat
              round
              icon="refresh"
              :loading="loading"
              @click="refresh"
              aria-label="Actualizar datos" /><q-btn
              v-if="section === 'patients'"
              unelevated
              color="primary"
              icon="add"
              label="Registrar paciente"
              no-caps
              @click="openPatient()" /><q-btn
              v-if="section === 'users'"
              unelevated
              color="primary"
              icon="person_add"
              label="Crear usuario"
              no-caps
              @click="openUser()" /><q-btn
              v-if="
                ['dashboard', 'requests', 'queue'].includes(section) &&
                ['estudiante', 'encargado'].includes(currentUser.role)
              "
              unelevated
              color="primary"
              icon="add"
              :label="
                isClinician
                  ? 'Ingreso directo'
                  : currentUser.role === 'docente'
                    ? 'Derivar al tópico'
                    : 'Solicitar permiso'
              "
              no-caps
              @click="openRequest" /></div
        ></div>
        <template v-if="section === 'dashboard'">
          <q-banner
            v-if="currentUser.role === 'coordinador' && !patients.length"
            class="bg-blue-1 text-blue-9 q-mb-lg"
            rounded
          >
            Comienza creando las cuentas del encargado y los docentes. Después
            registra a los pacientes. Su cuenta se creará automáticamente con su
            DNI.
            <template #action
              ><q-btn
                flat
                label="Crear usuario"
                no-caps
                @click="openUser()" /><q-btn
                flat
                label="Registrar paciente"
                no-caps
                @click="openPatient()"
            /></template>
          </q-banner>
          <q-banner
            v-if="
              stats?.alerts &&
              (stats.alerts.low_stock ||
                stats.alerts.expiring ||
                stats.alerts.expired)
            "
            class="bg-orange-1 text-orange-10 q-mb-lg"
            rounded
          >
            Inventario: {{ stats.alerts.low_stock }} productos con stock bajo,
            {{ stats.alerts.expiring }} próximos a vencer y
            {{ stats.alerts.expired }} con lotes vencidos.
            <template #action
              ><q-btn
                flat
                label="Revisar inventario"
                no-caps
                @click="navigate('inventory')"
            /></template>
          </q-banner>
          <q-banner
            v-if="stats?.alerts?.pending_referrals"
            class="bg-blue-1 text-blue-9 q-mb-lg"
            rounded
          >
            Tienes {{ stats.alerts.pending_referrals }} derivaciones con
            seguimiento pendiente.
            <template #action
              ><q-btn
                flat
                label="Ver derivaciones"
                no-caps
                @click="navigate('referrals')"
            /></template>
          </q-banner>
          <q-banner
            v-if="stats?.alerts?.pending_registry"
            class="bg-teal-1 text-primary q-mb-lg"
            rounded
          >
            Tienes {{ stats.alerts.pending_registry }} registros de atención o
            derivación por recibir.
            <template #action
              ><q-btn
                flat
                label="Revisar registros"
                no-caps
                @click="navigate('registry')"
            /></template>
          </q-banner>
          <div class="welcome-banner"
            ><div
              ><q-badge
                color="white"
                text-color="primary"
                label="UN CAMPUS MÁS SALUDABLE"
              /><h2
                >Hola, {{ currentUser.name.split(" ")[0] }} <span>✦</span></h2
              ><p>{{
                currentUser.role === "estudiante"
                  ? "Solicita atención y sigue el estado de tus permisos desde aquí."
                  : "Todo lo que necesitas para acompañar cada atención."
              }}</p></div
            ><img
              src="/portada-animada.svg"
              class="welcome-illustration"
              width="240"
              height="108"
              alt="Atención y bienestar estudiantil"
          /></div>
          <div class="stats-grid"
            ><q-card
              flat
              bordered
              v-for="card in metricCards"
              :key="card.label"
              class="metric"
              ><div class="row items-center justify-between"
                ><span class="muted">{{ card.label }}</span
                ><q-avatar :class="card.tone" size="42px"
                  ><q-icon :name="card.icon" size="23px" /></q-avatar></div
              ><strong>{{ card.value }}</strong
              ><small>{{ card.hint }}</small></q-card
            ></div
          >
          <div class="dashboard-grid"
            ><q-card flat bordered class="panel"
              ><div class="panel-heading"
                ><div
                  ><h3>Actividad reciente</h3
                  ><p class="muted"
                    >Seguimiento de las últimas solicitudes</p
                  ></div
                ><q-btn
                  flat
                  color="primary"
                  label="Ver todas"
                  no-caps
                  @click="navigate('requests')" /></div
              ><div v-if="!stats?.recent.length" class="empty-state"
                ><q-icon name="event_available" /><h3
                  >Todo listo para comenzar</h3
                ><p
                  >Las solicitudes aparecerán aquí cuando se registren.</p
                ></div
              ><div v-for="r in stats?.recent" :key="r.id" class="activity-row"
                ><q-avatar
                  color="teal-1"
                  text-color="primary"
                  icon="person_outline"
                /><div class="activity-name"
                  ><strong>{{ r.patient.name }}</strong
                  ><small>{{ formatDate(r.created_at) }}</small></div
                ><q-badge :class="statusClass(r.status)">{{
                  statusEmoji(r.status) + " " + statusLabels[r.status]
                }}</q-badge></div
              ></q-card
            ><q-card flat bordered class="panel"
              ><div class="panel-heading"
                ><div
                  ><h3>Ruta de atención</h3
                  ><p class="muted">Cada paso cuenta</p></div
                ><q-icon name="route" size="24px" color="primary" /></div
              ><div
                v-for="(step, i) in careSteps"
                :key="step.title"
                class="flow-step"
                ><span>{{ i + 1 }}</span
                ><div
                  ><strong>{{ step.title }}</strong
                  ><p>{{ step.text }}</p></div
                ></div
              ><div class="flow-note"
                ><q-icon name="priority_high" /> Las emergencias ingresan
                directamente al tópico.</div
              ></q-card
            ></div
          >
        </template>
        <AccountSettings
          v-else-if="section === 'settings'"
          :required-change="currentUser.uses_initial_password"
          @password-changed="finishPasswordSetup"
        />
        <CareRegistry
          v-else-if="section === 'registry'"
          :refresh-key="refreshVersion"
        />
        <OperationsPanel
          v-else-if="operationSections.includes(section)"
          :section="section"
          :refresh-key="refreshVersion"
        />
        <template v-else>
          <q-card flat bordered class="panel"
            ><div class="table-toolbar"
              ><q-input
                outlined
                dense
                v-model="search"
                placeholder="Buscar registros…"
                class="search-box"
                ><template #prepend><q-icon name="search" /></template></q-input
              ><q-select
                v-if="['requests', 'queue'].includes(section)"
                outlined
                dense
                v-model="statusFilter"
                :options="statusOptions"
                emit-value
                map-options
                label="Estado"
                style="min-width: 200px"
              /><span v-else class="muted"
                >{{ tableRows.length }} registros</span
              ></div
            >
            <q-table
              flat
              :rows="tableRows"
              :columns="tableColumns"
              row-key="id"
              :loading="loading"
              :rows-per-page-options="[10, 25, 50]"
              no-data-label="No hay registros en esta vista"
              rows-per-page-label="Filas por página"
            >
              <template #body-cell-name="props"
                ><q-td :props="props"
                  ><strong>{{ props.row.name }}</strong
                  ><div class="muted">{{
                    props.row.document || props.row.dni || props.row.email
                  }}</div></q-td
                ></template
              >
              <template #body-cell-patient="props"
                ><q-td :props="props"
                  ><strong>{{ props.row.patient?.name }}</strong
                  ><div class="muted"
                    >{{ props.row.laboratory || "Ubicación no registrada" }} ·
                    #{{ props.row.id }}</div
                  ></q-td
                ></template
              >
              <template #body-cell-priority="props"
                ><q-td :props="props"
                  ><q-badge :class="priorityClass(props.row.priority)">{{
                    priorityLabels[props.row.priority]
                  }}</q-badge></q-td
                ></template
              >
              <template #body-cell-status="props"
                ><q-td :props="props"
                  ><q-badge :class="statusClass(props.row.status)">{{
                    statusEmoji(props.row.status) +
                    " " +
                    statusLabels[props.row.status]
                  }}</q-badge></q-td
                ></template
              >
              <template #body-cell-role="props"
                ><q-td :props="props"
                  ><q-badge class="badge-teal">{{
                    roleLabels[props.row.role as Role]
                  }}</q-badge></q-td
                ></template
              >
              <template #body-cell-actions="props"
                ><q-td :props="props">
                  <template v-if="section === 'patients'"
                    ><q-btn
                      flat
                      round
                      color="primary"
                      icon="edit_note"
                      aria-label="Editar paciente"
                      @click="openPatient(props.row)" /><q-btn
                      v-if="isClinician"
                      flat
                      round
                      color="primary"
                      icon="history"
                      aria-label="Ver historial"
                      @click="viewHistory(props.row)"
                  /></template>
                  <q-btn
                    v-if="section === 'users'"
                    flat
                    color="primary"
                    icon="edit"
                    label="Editar"
                    no-caps
                    @click="openUser(props.row)"
                  />
                  <q-btn
                    v-if="section === 'history'"
                    flat
                    color="primary"
                    label="Ver atención"
                    no-caps
                    @click="showVisit(props.row)"
                  />
                  <template v-if="['requests', 'queue'].includes(section)"
                    ><q-btn
                      flat
                      color="primary"
                      label="Detalle"
                      no-caps
                      @click="
                        selectedRequest = props.row;
                        detailDialog = true;
                      " /><template
                      v-if="
                        currentUser.role === 'docente' &&
                        props.row.status === 'pendiente'
                      "
                      ><q-btn
                        unelevated
                        color="primary"
                        label="Conceder permiso"
                        no-caps
                        @click="authorize(props.row)"
                        :disable="busy" /></template
                    ><q-btn
                      v-if="
                        isClinician &&
                        ['autorizada', 'en_espera', 'en_atencion'].includes(
                          props.row.status
                        )
                      "
                      unelevated
                      color="primary"
                      :label="
                        props.row.status === 'en_atencion'
                          ? props.row.encounter?.status === 'borrador'
                            ? 'Editar evaluación'
                            : 'Continuar'
                          : 'Recibir'
                      "
                      no-caps
                      @click="openTriage(props.row)"
                  /></template> </q-td
              ></template>
            </q-table>
          </q-card>
        </template>
        <footer class="page-footer"
          >IESTPSS <span>·</span> Instituto de Educación Superior Paucar del
          Sarasara <q-space /><span class="gt-xs">{{
            roleLabels[currentUser.role]
          }}</span></footer
        >
      </q-page></q-page-container
    >
    <q-dialog v-model="encounterDialog" persistent @hide="refresh">
      <EncounterWorkspace
        v-if="selectedRequest && encounterDialog"
        :request-id="selectedRequest.id"
        @closed="finishEncounter"
      />
    </q-dialog>
    <q-dialog v-model="formDialog" persistent
      ><q-card class="form-dialog"
        ><q-form @submit="saveForm"
          ><q-card-section class="dialog-heading"
            ><div
              ><div class="eyebrow">GESTIÓN DEL TÓPICO</div
              ><h3>{{ formTitle }}</h3></div
            ><q-btn
              flat
              round
              icon="close"
              v-close-popup
              :disable="busy" /></q-card-section
          ><q-card-section class="q-gutter-md">
            <q-banner v-if="formKind === 'triage'" class="bg-teal-1"
              ><strong>{{ selectedRequest?.patient.name }}</strong
              ><br />{{ selectedRequest?.reason }}</q-banner
            >
            <q-banner
              v-if="formKind === 'triage' && selectedRequest?.patient.allergies"
              class="bg-orange-1 text-orange-10"
              ><q-icon name="warning_amber" /> Alergias:
              {{ selectedRequest.patient.allergies }}</q-banner
            >
            <q-banner
              v-if="
                formKind === 'request' &&
                currentUser.role === 'estudiante' &&
                !currentUser.patient_id
              "
              class="bg-orange-1"
              >Solicita al coordinador que vincule tu cuenta con una ficha de
              paciente.</q-banner
            >
            <template v-for="field in visibleFields" :key="field.key">
              <q-select
                v-if="field.options"
                outlined
                v-model="form[field.key]"
                :options="field.options"
                emit-value
                map-options
                :label="field.label"
                :rules="field.required ? [required] : []"
                :hint="field.hint"
                :disable="field.disabled"
              />
              <q-toggle
                v-else-if="field.type === 'toggle'"
                v-model="form[field.key]"
                :label="field.label"
                :disable="field.disabled"
              />
              <q-input
                v-else
                outlined
                :model-value="inputValue(field.key)"
                @update:model-value="value => (form[field.key] = value)"
                :type="field.type || 'text'"
                :rows="field.type === 'textarea' ? 2 : undefined"
                :label="field.label"
                :stack-label="field.type === 'date'"
                :rules="
                  field.key === 'dni'
                    ? [
                        required,
                        value =>
                          validDni(value) ||
                          'El DNI debe contener ocho dígitos.'
                      ]
                    : field.required
                      ? [required]
                      : []
                "
                :mask="field.key === 'dni' ? '########' : undefined"
                :inputmode="field.key === 'dni' ? 'numeric' : undefined"
                :hint="field.hint"
              />
            </template>
            <q-banner
              v-if="formKind === 'request' && form.emergency"
              class="bg-red-1 text-negative"
              >La emergencia ingresa directamente, sin autorización
              docente.</q-banner
            >
            <q-banner v-if="formKind === 'user' && form.id" class="bg-grey-2"
              >Los cambios cerrarán las sesiones de este usuario.</q-banner
            >
            <q-banner
              v-if="formKind === 'user' && (!form.id || form.reset_password)"
              class="bg-teal-1 text-primary"
              >La contraseña inicial será el DNI. El usuario deberá cambiarla al
              ingresar antes de acceder a cualquier otra función.</q-banner
            >
            <q-banner
              v-if="formError"
              class="bg-red-1 text-negative"
              role="alert"
              >{{ formError }}</q-banner
            > </q-card-section
          ><q-card-actions align="right"
            ><q-btn
              flat
              label="Cancelar"
              v-close-popup
              no-caps
              :disable="busy" /><q-btn
              unelevated
              color="primary"
              label="Guardar"
              type="submit"
              :loading="busy"
              no-caps /><q-btn
              v-if="
                formKind === 'triage' &&
                selectedRequest?.status === 'en_atencion'
              "
              outline
              color="primary"
              label="Registrar atención"
              no-caps
              @click="openEncounter"
              :disable="busy" /></q-card-actions></q-form></q-card
    ></q-dialog>
    <q-dialog v-model="detailDialog"
      ><q-card class="form-dialog"
        ><q-card-section class="dialog-heading"
          ><h3>Solicitud #{{ selectedRequest?.id }}</h3
          ><q-btn flat round icon="close" v-close-popup /></q-card-section
        ><q-card-section v-if="selectedRequest" class="detail-content"
          ><q-badge :class="statusClass(selectedRequest.status)">{{
            statusEmoji(selectedRequest.status) +
            " " +
            statusLabels[selectedRequest.status]
          }}</q-badge
          ><h3>{{ selectedRequest.patient.name }}</h3
          ><p><strong>Motivo:</strong> {{ selectedRequest.reason }}</p
          ><p
            ><strong>Solicitud registrada:</strong>
            {{ formatDate(selectedRequest.created_at) }}</p
          ><p v-if="selectedRequest.authorized_at"
            ><strong>Permiso concedido:</strong>
            {{ formatDate(selectedRequest.authorized_at) }}</p
          ><p
            ><strong>Docente:</strong>
            {{ selectedRequest.teacher?.name || "Ingreso directo" }}</p
          ><p
            ><strong>Laboratorio según horario:</strong>
            {{ selectedRequest.laboratory || "No registrado" }}</p
          ><p v-if="selectedRequest.schedule_reference"
            ><strong>Horario de referencia:</strong>
            {{ selectedRequest.schedule_reference }}</p
          ><p
            ><strong>Prioridad:</strong>
            {{ priorityLabels[selectedRequest.priority] }}</p
          ><p v-if="selectedRequest.authorization_note"
            ><strong>Observación:</strong>
            {{ selectedRequest.authorization_note }}</p
          ><RequestJourney
            :key="selectedRequest.id"
            :refresh-key="refreshVersion"
            :request-id="selectedRequest.id" /><q-btn
            v-if="isClinician && selectedRequest.encounter"
            flat
            color="primary"
            label="Ver atención clínica"
            no-caps
            @click="
              showVisit(selectedRequest.encounter)
            " /></q-card-section></q-card
    ></q-dialog>
    <q-dialog v-model="historyDialog"
      ><q-card class="form-dialog"
        ><q-card-section class="dialog-heading"
          ><div
            ><div class="eyebrow">HISTORIAL CLÍNICO</div
            ><h3>{{ historyPatient?.name }}</h3></div
          ><q-btn flat round icon="close" v-close-popup /></q-card-section
        ><q-card-section
          ><q-banner
            v-if="historyPatient?.allergies"
            class="bg-orange-1 q-mb-md"
            >Alergias: {{ historyPatient.allergies }}</q-banner
          ><p
            ><strong>Antecedentes:</strong>
            {{
              historyPatient?.background || "Sin antecedentes registrados"
            }}</p
          ><div v-if="!patientHistory.length" class="empty-state"
            >Sin atenciones previas.</div
          ><q-item
            v-for="v in patientHistory"
            :key="v.id"
            clickable
            @click="showVisit(v)"
            ><q-item-section avatar
              ><q-icon name="description" color="primary" /></q-item-section
            ><q-item-section
              ><q-item-label>{{ v.diagnosis }}</q-item-label
              ><q-item-label caption
                >{{ formatDate(v.created_at) }} ·
                {{ v.clinician?.name }}</q-item-label
              ></q-item-section
            ><q-item-section side
              ><q-icon
                name="chevron_right" /></q-item-section></q-item></q-card-section></q-card
    ></q-dialog>
    <q-dialog v-model="visitDialog"
      ><q-card class="form-dialog"
        ><q-card-section class="dialog-heading"
          ><h3>Atención #{{ selectedVisit?.id }}</h3
          ><q-btn flat round icon="close" v-close-popup /></q-card-section
        ><q-card-section v-if="selectedVisit" class="detail-content"
          ><p
            ><strong>Paciente:</strong>
            {{
              selectedVisit.patient?.name ||
              historyPatient?.name ||
              selectedRequest?.patient.name
            }}</p
          ><p
            ><strong>Profesional:</strong>
            {{ selectedVisit.clinician?.name || currentUser.name }}</p
          ><p
            ><strong>Fecha:</strong>
            {{ formatDate(selectedVisit.created_at) }}</p
          ><h4>Evaluación y síntomas</h4><p>{{ selectedVisit.symptoms }}</p
          ><p
            ><strong>Laboratorio de procedencia:</strong>
            {{ selectedVisit.laboratory || "No registrado"
            }}<template v-if="selectedVisit.schedule_reference">
              · {{ selectedVisit.schedule_reference }}</template
            ></p
          ><h4>Diagnóstico</h4><p>{{ selectedVisit.diagnosis }}</p
          ><h4>Indicaciones</h4><p>{{ selectedVisit.indications }}</p
          ><template v-if="selectedVisit.no_medication_reason"
            ><h4>Atención sin medicación</h4
            ><p>{{ selectedVisit.no_medication_reason }}</p></template
          ><q-badge :class="statusClass(selectedVisit.outcome)">{{
            statusEmoji(selectedVisit.outcome) +
            " " +
            statusLabels[selectedVisit.outcome]
          }}</q-badge
          ><template v-if="selectedVisit.outcome === 'derivada'"
            ><h4>Derivación externa</h4
            ><p>{{ selectedVisit.referral_destination }}</p
            ><p>{{ selectedVisit.referral_reason }}</p></template
          ></q-card-section
        ></q-card
      ></q-dialog
    >
  </q-layout>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import OperationsPanel from "@/components/OperationsPanel.vue";
import AccountSettings from "@/components/AccountSettings.vue";
import EncounterWorkspace from "@/components/EncounterWorkspace.vue";
import RequestJourney from "@/components/RequestJourney.vue";
import CareRegistry from "@/components/CareRegistry.vue";
import type { QInputProps, QTableColumn } from "quasar";
import {
  api,
  currentUser,
  sessionNotice,
  roleLabels,
  statusLabels,
  statusEmoji,
  priorityLabels,
  type Account,
  type CareRequest,
  type Dashboard,
  type Patient,
  type Role,
  type Visit
} from "@/services/topico";
const booting = ref(true),
  drawer = ref(false),
  busy = ref(false),
  loading = ref(false),
  error = ref(""),
  success = ref(""),
  formError = ref("");
const credentials = reactive({
    dni: "",
    password: ""
  }),
  showPassword = ref(false),
  section = ref("dashboard"),
  search = ref(""),
  statusFilter = ref("all");
const stats = ref<Dashboard | null>(null),
  patients = ref<Patient[]>([]),
  requests = ref<CareRequest[]>([]),
  visits = ref<Visit[]>([]),
  users = ref<(Account & { active: boolean })[]>([]);
const directory = ref<{
  patients: { id: number; name: string }[];
  teachers: Account[];
}>({ patients: [], teachers: [] });
const isClinician = computed(() => currentUser.value?.role === "encargado");
const operationSections = [
  "inventory",
  "prescriptions",
  "referrals",
  "reports",
  "records",
  "audit"
];
const refreshVersion = ref(0);
const isStaff = computed(() =>
  ["coordinador", "encargado"].includes(currentUser.value?.role || "")
);
const navigation = computed(() => [
  { key: "dashboard", label: "Vista general", icon: "space_dashboard" },
  ...(isStaff.value
    ? [{ key: "patients", label: "Pacientes", icon: "people_outline" }]
    : []),
  {
    key: "requests",
    label:
      currentUser.value?.role === "estudiante"
        ? "Mis solicitudes"
        : "Solicitudes y permisos",
    icon: "assignment"
  },
  ...(isClinician.value
    ? [
        {
          key: "queue",
          label: "Cola de atención",
          icon: "format_list_numbered"
        },
        { key: "history", label: "Historial de atenciones", icon: "history" }
      ]
    : []),
  ...(isClinician.value
    ? [
        {
          key: "prescriptions",
          label: "Recetas y entregas",
          icon: "medication"
        },
        {
          key: "referrals",
          label: "Derivaciones externas",
          icon: "local_shipping"
        }
      ]
    : []),
  ...(isStaff.value
    ? [
        {
          key: "inventory",
          label: "Medicamentos e insumos",
          icon: "inventory_2"
        },
        { key: "reports", label: "Reportes", icon: "bar_chart" },
        {
          key: "records",
          label: "Intercambio de registros",
          icon: "import_export"
        }
      ]
    : []),
  ...(currentUser.value?.role === "coordinador"
    ? [
        {
          key: "registry",
          label: "Registro de atenciones",
          icon: "fact_check"
        },
        { key: "users", label: "Usuarios y permisos", icon: "manage_accounts" },
        { key: "audit", label: "Auditoría", icon: "policy" }
      ]
    : []),
  { key: "settings", label: "Configuración", icon: "settings" }
]);
const pageTitle = computed(
  () =>
    navigation.value.find(n => n.key === section.value)?.label ||
    "Vista general"
);
const subtitles: Record<string, string> = {
  dashboard: "Una mirada al bienestar de nuestra comunidad.",
  patients: "Identificación y datos de contacto de los estudiantes.",
  requests: "Gestiona permisos y sigue cada solicitud de atención.",
  queue: "Prioriza, recibe y acompaña a cada paciente.",
  history: "Consulta las atenciones y derivaciones registradas.",
  users: "Organiza el acceso del personal y los estudiantes.",
  inventory: "Controla existencias, lotes, entradas y vencimientos.",
  prescriptions: "Vincula las indicaciones y entregas con cada atención.",
  referrals: "Acompaña las derivaciones a establecimientos externos.",
  reports: "Consulta indicadores y consumo por período.",
  records: "Importa y exporta los registros del tópico.",
  audit: "Consulta la trazabilidad de los accesos y cambios.",
  settings: "Administra la contraseña de tu cuenta.",
  registry: "Recibe y consulta las atenciones y derivaciones cerradas."
};
const today = new Intl.DateTimeFormat("es-ES", {
  day: "numeric",
  month: "long",
  year: "numeric"
}).format(new Date());
const initials = computed(() =>
  currentUser.value?.name
    .split(" ")
    .slice(0, 2)
    .map(s => s[0])
    .join("")
    .toUpperCase()
);
const careSteps = [
  {
    title: "Solicitud y permiso",
    text: "El estudiante solicita permiso a su docente."
  },
  {
    title: "Derivación al tópico",
    text: "El docente autoriza y deriva al estudiante."
  },
  {
    title: "Recepción y prioridad",
    text: "El encargado organiza la atención."
  },
  {
    title: "Evaluación y diagnóstico",
    text: "El encargado registra la evaluación y decide la ruta de atención."
  },
  {
    title: "Tratamiento o derivación",
    text: "Se registra la receta o la derivación externa antes del cierre."
  },
  {
    title: "Cierre y recepción del registro",
    text: "El historial se actualiza y el coordinador recibe el resumen."
  }
];
const metricCards = computed(() => [
  {
    label: "Solicitudes de hoy",
    value: stats.value?.today ?? 0,
    icon: "event_note",
    tone: "bg-teal-1 text-primary",
    hint: "Registradas durante el día"
  },
  {
    label: "En espera",
    value: stats.value?.waiting ?? 0,
    icon: "schedule",
    tone: "bg-aqua-soft text-primary",
    hint: "Autorizadas y pendientes de atención"
  },
  {
    label: "Permisos pendientes",
    value: stats.value?.pending ?? 0,
    icon: "assignment_turned_in",
    tone: "bg-aqua-soft text-primary",
    hint: "Por autorizar por el docente"
  },
  {
    label: "Atenciones cerradas",
    value: stats.value?.completed ?? 0,
    icon: "task_alt",
    tone: "bg-aqua-soft text-primary",
    hint: "Finalizadas o derivadas hoy"
  }
]);
const required = (v: unknown) => !!v || v === 0 || "Este campo es obligatorio";
const validDni = (value: unknown) =>
  typeof value === "string" && /^[0-9]{8}$/.test(value);
const formatDate = (v: string) =>
  new Intl.DateTimeFormat("es-ES", {
    day: "2-digit",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit"
  }).format(new Date(v));
const statusClass = (s: string) =>
  ["finalizada", "autorizada"].includes(s)
    ? "badge-teal"
    : s === "cancelada"
      ? "badge-grey"
      : s === "derivada"
        ? "badge-red"
        : s === "en_atencion"
          ? "badge-blue"
          : "badge-orange";
const priorityClass = (p: string) =>
  p === "emergencia"
    ? "badge-red"
    : p === "urgente"
      ? "badge-orange"
      : "badge-teal";
const statusOptions = [
  { label: "Todos los estados", value: "all" },
  ...Object.entries(statusLabels).map(([value, label]) => ({ value, label }))
];
const priorityOptions = Object.entries(priorityLabels).map(
  ([value, label]) => ({ value, label })
);
const roleOptions = Object.entries(roleLabels).map(([value, label]) => ({
  value,
  label
}));
const matches = (text: string) =>
  text.toLocaleLowerCase().includes(search.value.toLocaleLowerCase());
const column = (
  name: string,
  label: string,
  field: QTableColumn["field"] = name
): QTableColumn => ({
  name,
  label,
  field,
  align: name === "actions" ? "right" : "left",
  sortable: name !== "actions"
});
const tableColumns = computed<QTableColumn[]>(() =>
  section.value === "patients"
    ? [
        column("name", "Paciente"),
        column("phone", "Teléfono"),
        column("emergency_contact", "Contacto de emergencia"),
        column("actions", "Acciones", "id")
      ]
    : section.value === "users"
      ? [
          column("name", "Usuario"),
          column("dni", "DNI"),
          column("role", "Rol"),
          {
            ...column("password_state", "Contraseña", "uses_initial_password"),
            format: value => (value ? "Inicial (DNI)" : "Personal")
          },
          {
            ...column("active", "Estado"),
            format: v => (v ? "Activo" : "Inactivo")
          },
          column("actions", "Acciones", "id")
        ]
      : section.value === "history"
        ? [
            column("patient", "Paciente", r => r.patient?.name),
            { ...column("date", "Fecha", "created_at"), format: formatDate },
            column("diagnosis", "Diagnóstico"),
            {
              ...column("outcome", "Resultado"),
              format: v => statusEmoji(String(v)) + " " + (statusLabels[v] || v)
            },
            column("actions", "Detalle", "id")
          ]
        : [
            column("patient", "Paciente", r => r.patient?.name),
            { ...column("date", "Registro", "created_at"), format: formatDate },
            {
              ...column(
                "permission_time",
                "Permiso concedido",
                "authorized_at"
              ),
              format: value => (value ? formatDate(String(value)) : "Pendiente")
            },
            column("priority", "Prioridad"),
            column("status", "Estado"),
            column("actions", "Acciones", "id")
          ]
);
const rank: Record<string, number> = { emergencia: 0, urgente: 1, normal: 2 };
const tableRows = computed(() =>
  section.value === "patients"
    ? patients.value.filter(p => matches(`${p.name} ${p.document}`))
    : section.value === "users"
      ? users.value.filter(u =>
          matches(`${u.name} ${u.dni || ""} ${u.email || ""}`)
        )
      : section.value === "history"
        ? visits.value.filter(
            v =>
              v.status === "cerrada" &&
              matches(`${v.patient?.name} ${v.diagnosis}`)
          )
        : requests.value
            .filter(
              r =>
                (section.value !== "queue" ||
                  ["autorizada", "en_espera", "en_atencion"].includes(
                    r.status
                  )) &&
                (statusFilter.value === "all" ||
                  r.status === statusFilter.value) &&
                matches(`${r.patient.name} ${r.laboratory || ""}`)
            )
            .sort((a, b) =>
              section.value === "queue"
                ? (rank[a.priority] ?? 2) - (rank[b.priority] ?? 2) ||
                  a.id - b.id
                : b.id - a.id
            )
);
function navigate(key: string) {
  if (currentUser.value?.uses_initial_password && key !== "settings") return;
  section.value = key;
  search.value = "";
  statusFilter.value = "all";
}
async function refresh() {
  await reloadData(false);
}
let refreshing = false;
const syncError = ref("");
const lastSync = ref("");
async function reloadData(background: boolean) {
  if (!currentUser.value || currentUser.value.uses_initial_password) return;
  if (refreshing) return;
  refreshing = true;
  if (!background) {
    loading.value = true;
    error.value = "";
  }
  const read = <T>(path: string) =>
    api<T>(path, "GET", undefined, { background });
  try {
    const [s, r, d] = await Promise.all([
      read<Dashboard>("/dashboard"),
      read<CareRequest[]>("/requests"),
      read<typeof directory.value>("/directory")
    ]);
    stats.value = s;
    requests.value = r;
    directory.value = d;
    if (
      isStaff.value &&
      (!background || ["patients", "users"].includes(section.value))
    )
      patients.value = await read<Patient[]>("/patients");
    if (isClinician.value && (!background || section.value === "history"))
      visits.value = await read<Visit[]>("/encounters");
    if (
      currentUser.value?.role === "coordinador" &&
      (!background || section.value === "users")
    )
      users.value = await read<typeof users.value>("/users");
    if (selectedRequest.value && !encounterDialog.value && !formDialog.value) {
      selectedRequest.value =
        r.find(row => row.id === selectedRequest.value?.id) ||
        selectedRequest.value;
    }
    refreshVersion.value++;
    syncError.value = "";
    lastSync.value = new Date().toLocaleTimeString("es-PE");
  } catch (e) {
    if (background)
      syncError.value = "Sin conexión. Reintentando automáticamente…";
    else error.value = (e as Error).message;
  } finally {
    refreshing = false;
    loading.value = false;
  }
}
async function login() {
  if (!validDni(credentials.dni)) {
    error.value = "Ingresa un DNI válido de ocho dígitos.";
    return;
  }
  busy.value = true;
  error.value = "";
  success.value = "";
  try {
    const data = await api<{ token: string; user: Account }>(
      "/login",
      "POST",
      credentials
    );
    clearSession();
    sessionStorage.setItem("topico_token", data.token);
    currentUser.value = data.user;
    sessionNotice.value = "";
    credentials.password = "";
    if (data.user.uses_initial_password) await api<Account>("/me");
    await refresh();
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}

function clearSession() {
  credentials.password = "";
  success.value = "";
  sessionStorage.removeItem("topico_token");
  currentUser.value = null;
  patients.value = [];
  requests.value = [];
  visits.value = [];
  users.value = [];
  directory.value = { patients: [], teachers: [] };
  section.value = "dashboard";
  stats.value = null;
  formDialog.value = false;
  detailDialog.value = false;
  encounterDialog.value = false;
  selectedRequest.value = null;
  historyDialog.value = false;
  visitDialog.value = false;
  patientHistory.value = [];
  historyPatient.value = null;
  selectedVisit.value = null;
  selectedRequest.value = null;
  for (const key of Object.keys(form)) delete form[key];
}
watch(currentUser, (user, previous) => {
  if (!user && previous) clearSession();
});
watch(
  () => currentUser.value?.uses_initial_password,
  required => {
    if (required) {
      section.value = "settings";
      formDialog.value = false;
      detailDialog.value = false;
      encounterDialog.value = false;
      historyDialog.value = false;
      visitDialog.value = false;
    }
  },
  { flush: "sync" }
);
async function logout() {
  try {
    await api("/logout", "POST");
  } catch {}
  clearSession();
}
async function finishPasswordSetup() {
  success.value =
    "Contraseña personal guardada. Ya puedes utilizar el sistema.";
  await refresh();
}
interface Field {
  key: string;
  label: string;
  type?: QInputProps["type"] | "toggle";
  required?: boolean;
  hint?: string;
  disabled?: boolean;
  options?: { label: string; value: string | number }[];
  show?: () => boolean;
}
type FormValue = string | number | boolean | null;
const form = reactive<Record<string, FormValue>>({}),
  fields = ref<Field[]>([]),
  formKind = ref(""),
  formTitle = ref(""),
  formDialog = ref(false),
  detailDialog = ref(false),
  encounterDialog = ref(false),
  historyDialog = ref(false),
  visitDialog = ref(false);
const inputValue = (key: string): string | number | null => {
  const value = form[key];
  return typeof value === "string" || typeof value === "number" ? value : null;
};
const visibleFields = computed(() =>
  fields.value.filter(f => !f.show || f.show())
);
const selectedRequest = ref<CareRequest | null>(null),
  historyPatient = ref<Patient | null>(null),
  patientHistory = ref<Visit[]>([]),
  selectedVisit = ref<Visit | null>(null);
function openForm(
  kind: string,
  title: string,
  values: Record<string, FormValue>,
  schema: Field[]
) {
  for (const key of Object.keys(form)) delete form[key];
  Object.assign(form, values);
  fields.value = schema;
  formKind.value = kind;
  formTitle.value = title;
  formError.value = "";
  formDialog.value = true;
}
function openPatient(p?: Patient) {
  const values: Record<string, FormValue> = {
    id: p?.id || 0,
    name: p?.name || "",
    document: p?.document || "",
    birth_date: p?.birth_date?.slice(0, 10) || "",
    phone: p?.phone || "",
    emergency_contact: p?.emergency_contact || "",
    emergency_phone: p?.emergency_phone || ""
  };
  if (isClinician.value) {
    values.allergies = p?.allergies || "";
    values.background = p?.background || "";
  }
  openForm("patient", p ? "Editar paciente" : "Registrar paciente", values, [
    { key: "name", label: "Nombres y apellidos *", required: true },
    {
      key: "document",
      label: "Documento de identidad *",
      required: true,
      hint: "Con un DNI de 8 dígitos se crea la cuenta automáticamente. La contraseña inicial será el DNI y deberá cambiarse al ingresar."
    },
    { key: "birth_date", label: "Fecha de nacimiento", type: "date" },
    { key: "phone", label: "Teléfono" },
    { key: "emergency_contact", label: "Contacto de emergencia" },
    { key: "emergency_phone", label: "Teléfono de emergencia" },
    ...(isClinician.value
      ? [
          {
            key: "allergies",
            label: "Alergias conocidas",
            type: "textarea" as const
          },
          {
            key: "background",
            label: "Antecedentes relevantes",
            type: "textarea" as const
          }
        ]
      : [])
  ]);
}
function openRequest() {
  if (currentUser.value?.role === "estudiante") {
    openForm("request", "Solicitar permiso al docente", { teacher_id: null }, [
      {
        key: "teacher_id",
        label: "Docente que concede el permiso *",
        required: true,
        options: directory.value.teachers.map(teacher => ({
          value: teacher.id,
          label: teacher.name
        })),
        hint: "El encargado completará la información al llegar al tópico. La hora de solicitud se registra automáticamente."
      }
    ]);
    return;
  }
  if (!isClinician.value) return;
  openForm(
    "request",
    isClinician.value
      ? "Ingreso directo al tópico"
      : currentUser.value?.role === "docente"
        ? "Derivar estudiante al tópico"
        : "Solicitar permiso al docente",
    {
      patient_id: currentUser.value?.patient_id || null,
      teacher_id: null,
      laboratory: "",
      schedule_reference: "",
      reason: "",
      emergency: false
    },
    [
      {
        key: "patient_id",
        label: "Paciente *",
        required: true,
        options: directory.value.patients.map(p => ({
          value: p.id,
          label: p.name
        })),
        show: () => currentUser.value?.role !== "estudiante"
      },
      {
        key: "teacher_id",
        label: "Docente que autoriza *",
        required: true,
        options: directory.value.teachers.map(p => ({
          value: p.id,
          label: p.name
        })),
        show: () => currentUser.value?.role === "estudiante"
      },
      {
        key: "laboratory",
        label: "Laboratorio actual (según horario)",
        required: !isClinician.value,
        hint: "Indica dónde te encuentras en este horario. Este dato corresponde solo a esta solicitud."
      },
      {
        key: "schedule_reference",
        label: "Horario de referencia (opcional)",
        hint: "Ejemplo: 08:00 a 09:30. Corresponde al momento de esta solicitud."
      },
      {
        key: "reason",
        label: "Motivo de consulta *",
        type: "textarea",
        required: true
      },
      {
        key: "emergency",
        label: "Ingreso por emergencia",
        type: "toggle",
        show: () => isClinician.value
      }
    ]
  );
}
function openTriage(r: CareRequest) {
  selectedRequest.value = r;
  if (r.status === "en_atencion") {
    openEncounter();
    return;
  }
  openForm(
    "triage",
    "Recepción y prioridad",
    {
      priority: r.priority,
      reason:
        r.reason === "Pendiente de registrar por el encargado del tópico."
          ? ""
          : r.reason,
      laboratory: r.laboratory || "",
      schedule_reference: r.schedule_reference || "",
      status: r.status === "en_atencion" ? "en_atencion" : "en_espera"
    },
    [
      {
        key: "reason",
        label: "Motivo de atención *",
        type: "textarea",
        required: true
      },
      {
        key: "laboratory",
        label: "Laboratorio de procedencia (según horario)"
      },
      { key: "schedule_reference", label: "Horario de referencia (opcional)" },
      {
        key: "priority",
        label: "Prioridad de atención",
        options: priorityOptions
      },
      {
        key: "status",
        label: "Estado",
        options: [
          { label: "En espera", value: "en_espera" },
          { label: "En atención", value: "en_atencion" }
        ],
        disabled: r.status === "en_atencion"
      }
    ]
  );
}
function openEncounter() {
  formDialog.value = false;
  encounterDialog.value = true;
}
function finishEncounter() {
  encounterDialog.value = false;
  success.value =
    "Atención cerrada. El registro está disponible para el coordinador.";
}
async function authorize(r: CareRequest) {
  busy.value = true;
  error.value = "";
  try {
    await api("/requests/" + r.id, "PATCH", { status: "autorizada" });
    success.value = "Permiso autorizado y estudiante derivado al tópico.";
    await refresh();
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
function openUser(u?: Account & { active: boolean }) {
  openForm(
    "user",
    u ? "Editar usuario" : "Crear usuario",
    {
      id: u?.id || 0,
      name: u?.name || "",
      dni: u?.dni || "",
      email: u?.email || "",
      role:
        u?.role ||
        (!users.value.some(user => user.role === "encargado")
          ? "encargado"
          : !users.value.some(user => user.role === "docente")
            ? "docente"
            : "estudiante"),
      reset_password: false,
      active: u?.active ?? true,
      patient_id: u?.patient_id || null
    },
    [
      { key: "name", label: "Nombres y apellidos *", required: true },
      {
        key: "dni",
        label: "DNI *",
        required: true,
        hint: "Ocho dígitos. Se utilizará para iniciar sesión."
      },
      {
        key: "email",
        label: "Correo electrónico (opcional)",
        type: "email",
        required: false
      },
      {
        key: "role",
        label: "Rol",
        options: roleOptions,
        disabled: u?.id === currentUser.value?.id
      },
      {
        key: "patient_id",
        label: "Ficha de paciente *",
        options: patients.value
          .filter(p => !p.user_id || p.user_id === u?.id)
          .map(p => ({ value: p.id, label: p.name + " · " + p.document })),
        required: true,
        hint: "Registra primero al paciente para vincular su cuenta.",
        show: () => form.role === "estudiante"
      },
      {
        key: "reset_password",
        label: "Restablecer contraseña inicial al DNI",
        type: "toggle",
        show: () => !!u && u.id !== currentUser.value?.id
      },
      {
        key: "active",
        label: "Cuenta activa",
        type: "toggle",
        disabled: u?.id === currentUser.value?.id
      }
    ]
  );
}
async function saveForm() {
  busy.value = true;
  formError.value = "";
  try {
    const kind = formKind.value;
    const body = { ...form };
    if (kind === "patient")
      await api(
        "/patients" + (body.id ? "/" + body.id : ""),
        body.id ? "PUT" : "POST",
        body
      );
    if (kind === "request") await api("/requests", "POST", body);
    if (kind === "cancel")
      await api("/requests/" + selectedRequest.value!.id, "PATCH", {
        status: "cancelada",
        ...body
      });
    if (kind === "triage")
      selectedRequest.value = await api<CareRequest>(
        "/requests/" + selectedRequest.value!.id,
        "PATCH",
        body
      );
    if (kind === "user")
      await api(
        "/users" + (body.id ? "/" + body.id : ""),
        body.id ? "PUT" : "POST",
        body
      );
    formDialog.value = false;
    success.value = "Registro guardado correctamente.";
    if (kind === "patient" && !body.id && validDni(String(body.document))) {
      success.value =
        "Paciente registrado y cuenta vinculada. Para una cuenta nueva, el usuario y la contraseña inicial son su DNI; deberá cambiar la contraseña dentro de Configuración al ingresar.";
    }
    if (kind === "user" && body.id === currentUser.value?.id) {
      clearSession();
      success.value = "Datos actualizados. Ingresa nuevamente.";
    } else {
      await refresh();
      if (kind === "triage" && body.status === "en_atencion") openEncounter();
    }
  } catch (e) {
    formError.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
async function viewHistory(p: Patient) {
  try {
    patientHistory.value = await api<Visit[]>("/patients/" + p.id + "/history");
    historyPatient.value = p;
    historyDialog.value = true;
  } catch (e) {
    error.value = (e as Error).message;
  }
}
function showVisit(v: Visit) {
  selectedVisit.value = v;
  visitDialog.value = true;
}
onMounted(async () => {
  if (sessionStorage.getItem("topico_token")) {
    try {
      currentUser.value = await api<Account>("/me");
      await refresh();
    } catch {
      clearSession();
    }
  }
  booting.value = false;
});
const pollUpdates = () => {
  if (
    !document.hidden &&
    navigator.onLine &&
    !busy.value &&
    !formDialog.value &&
    !encounterDialog.value &&
    section.value !== "settings"
  ) {
    void reloadData(true);
  }
};
let refreshTimer: ReturnType<typeof setInterval> | undefined;
const showOffline = () => {
  syncError.value = "Sin conexión. Se actualizará al reconectar.";
};
onMounted(() => {
  refreshTimer = setInterval(pollUpdates, 5000);
  window.addEventListener("online", pollUpdates);
  window.addEventListener("offline", showOffline);
  document.addEventListener("visibilitychange", pollUpdates);
});
onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer);
  window.removeEventListener("online", pollUpdates);
  window.removeEventListener("offline", showOffline);
  document.removeEventListener("visibilitychange", pollUpdates);
});
</script>
