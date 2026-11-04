<template>
  <q-card flat bordered class="panel account-settings">
    <div class="panel-heading">
      <div
        ><h3>Cambiar contraseña</h3
        ><p class="muted">{{
          requiredChange
            ? "Debes crear una contraseña personal para acceder al sistema."
            : "Puedes actualizar tu contraseña cuando lo desees."
        }}</p></div
      >
      <q-icon name="lock_outline" color="primary" size="26px" />
    </div>
    <q-card-section>
      <div class="account-summary"
        ><q-avatar
          color="teal-1"
          text-color="primary"
          icon="person_outline"
        /><div
          ><strong>{{ currentUser?.name }}</strong
          ><p class="muted"
            >DNI: {{ currentUser?.dni }} ·
            {{ currentUser ? roleLabels[currentUser.role] : "" }}</p
          ></div
        ></div
      >
      <q-banner
        v-if="success"
        rounded
        class="bg-teal-1 text-primary q-mb-lg"
        role="status"
        >{{ success }}</q-banner
      >
      <q-banner
        v-if="error"
        rounded
        class="bg-red-1 text-negative q-mb-lg"
        role="alert"
        >{{ error }}</q-banner
      >
      <q-form ref="passwordForm" @submit="savePassword" class="q-gutter-y-md">
        <q-input
          outlined
          v-model="form.current_password"
          :type="visible ? 'text' : 'password'"
          autocomplete="current-password"
          :label="
            requiredChange ? 'Contraseña inicial (tu DNI)' : 'Contraseña actual'
          "
          :rules="[required]"
          maxlength="255"
        />
        <q-input
          outlined
          v-model="form.password"
          :type="visible ? 'text' : 'password'"
          autocomplete="new-password"
          label="Nueva contraseña"
          hint="Utiliza al menos 10 caracteres."
          :rules="[
            required,
            value =>
              String(value).length >= 10 || 'Utiliza al menos 10 caracteres.'
          ]"
          maxlength="255"
        />
        <q-input
          outlined
          v-model="form.password_confirmation"
          :type="visible ? 'text' : 'password'"
          autocomplete="new-password"
          label="Confirmar nueva contraseña"
          :rules="[
            required,
            value => value === form.password || 'Las contraseñas no coinciden.'
          ]"
          maxlength="255"
        />
        <q-checkbox
          v-model="visible"
          label="Mostrar contraseñas"
          color="primary"
        />
        <div class="row justify-end q-gutter-sm"
          ><q-btn
            flat
            label="Limpiar"
            no-caps
            :disable="saving"
            @click="clearForm" /><q-btn
            unelevated
            color="primary"
            type="submit"
            label="Guardar contraseña"
            icon="save"
            no-caps
            :loading="saving"
        /></div>
      </q-form>
      <template v-if="!requiredChange">
        <q-separator class="q-my-lg" />
        <div class="row items-center justify-between q-mb-sm">
          <h3 class="q-my-none">Sesiones activas</h3>
          <q-btn
            flat
            round
            icon="refresh"
            color="primary"
            aria-label="Actualizar sesiones"
            :loading="loadingSessions"
            @click="loadSessions"
          />
        </div>
        <p class="muted"
          >Puedes cerrar tus otras sesiones. Por seguridad, cada sesión caduca
          tras {{ idleMinutes }} minutos sin actividad.</p
        >
        <q-banner
          v-if="sessionError"
          rounded
          class="bg-red-1 text-negative q-mb-md"
          role="alert"
          >{{ sessionError }}</q-banner
        >
        <q-banner
          v-if="sessionMessage"
          rounded
          class="bg-teal-1 text-primary q-mb-md"
          role="status"
          >{{ sessionMessage }}</q-banner
        >
        <q-list separator>
          <q-item
            v-for="session in sessions"
            :key="session.id"
            class="q-px-none"
          >
            <q-item-section avatar
              ><q-icon name="devices" color="primary"
            /></q-item-section>
            <q-item-section>
              <q-item-label>{{
                session.current ? "Esta sesión" : "Otra sesión"
              }}</q-item-label>
              <q-item-label caption
                >Inicio: {{ formatDate(session.created_at) }}</q-item-label
              >
              <q-item-label caption
                >Última actividad:
                {{ formatDate(session.last_activity_at) }}</q-item-label
              >
            </q-item-section>
            <q-item-section side v-if="!session.current">
              <q-btn
                flat
                no-caps
                color="negative"
                label="Cerrar"
                :loading="revoking === session.id"
                :disable="revoking !== null"
                @click="revokeSession(session.id)"
              />
            </q-item-section>
          </q-item>
        </q-list>
      </template>
    </q-card-section>
  </q-card>
</template>

<script setup lang="ts">
import { nextTick, onMounted, reactive, ref, watch } from "vue";
import type { QForm } from "quasar";
import { api, currentUser, roleLabels } from "@/services/topico";

const props = withDefaults(defineProps<{ requiredChange?: boolean }>(), {
  requiredChange: false
});
const emit = defineEmits<{ passwordChanged: [] }>();

const form = reactive({
  current_password: "",
  password: "",
  password_confirmation: ""
});
const passwordForm = ref<QForm | null>(null);
const visible = ref(false),
  saving = ref(false),
  error = ref(""),
  success = ref("");
const required = (value: unknown) => !!value || "Este campo es obligatorio";
interface ActiveSession {
  id: number;
  current: boolean;
  created_at: string;
  last_activity_at: string;
  expires_at: string;
}
const sessions = ref<ActiveSession[]>([]);
const idleMinutes = ref(30);
const loadingSessions = ref(false);
const revoking = ref<number | null>(null);
const sessionError = ref("");
const sessionMessage = ref("");
const formatDate = (value: string) => new Date(value).toLocaleString("es-PE");

async function loadSessions() {
  loadingSessions.value = true;
  sessionError.value = "";
  try {
    const data = await api<{ sessions: ActiveSession[]; idle_minutes: number }>(
      "/sessions"
    );
    sessions.value = data.sessions;
    idleMinutes.value = data.idle_minutes;
  } catch (exception) {
    sessionError.value = (exception as Error).message;
  } finally {
    loadingSessions.value = false;
  }
}

async function revokeSession(id: number) {
  revoking.value = id;
  sessionError.value = "";
  sessionMessage.value = "";
  try {
    await api("/sessions/" + id, "DELETE");
    sessions.value = sessions.value.filter(session => session.id !== id);
    sessionMessage.value = "La otra sesión se cerró correctamente.";
  } catch (exception) {
    sessionError.value = (exception as Error).message;
  } finally {
    revoking.value = null;
  }
}

onMounted(() => {
  if (!props.requiredChange) void loadSessions();
});
watch(
  () => props.requiredChange,
  (required, previous) => {
    if (previous && !required) void loadSessions();
  }
);

async function clearForm() {
  Object.assign(form, {
    current_password: "",
    password: "",
    password_confirmation: ""
  });
  visible.value = false;
  await nextTick();
  passwordForm.value?.resetValidation();
}

async function savePassword() {
  saving.value = true;
  error.value = "";
  success.value = "";
  try {
    await api("/password", "PUT", { ...form });
    sessions.value = sessions.value.filter(session => session.current);
    await clearForm();
    if (currentUser.value) currentUser.value.uses_initial_password = false;
    success.value =
      "Contraseña actualizada. En tu próximo inicio de sesión utiliza tu DNI y tu nueva contraseña.";
    emit("passwordChanged");
  } catch (exception) {
    error.value = (exception as Error).message;
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.account-settings {
  max-width: 680px;
}
.account-summary {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 24px;
}
.account-summary strong {
  font-size: 14px;
}
.account-summary p {
  margin: 3px 0 0;
}
</style>
