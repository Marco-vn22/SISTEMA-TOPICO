<template>
  <section class="request-journey">
    <div class="row items-center q-mb-md"
      ><h3>Seguimiento del proceso</h3><q-space /><q-btn
        flat
        round
        dense
        icon="refresh"
        aria-label="Actualizar seguimiento"
        :loading="loading"
        @click="load"
    /></div>
    <q-banner v-if="error" class="bg-red-1 text-negative" rounded>{{
      error
    }}</q-banner>
    <template v-else-if="tracking">
      <div class="journey-steps"
        ><div
          v-for="(step, index) in steps"
          :key="step.title"
          :class="['journey-step', { 'journey-done': step.done }]"
          ><span
            ><q-icon v-if="step.done" name="check" /><template v-else>{{
              index + 1
            }}</template></span
          ><div
            ><strong>{{ step.title }}</strong
            ><small>{{ step.owner }}</small></div
          ></div
        ></div
      >
      <q-banner
        v-if="tracking.request.emergency"
        class="bg-orange-1 q-my-md"
        rounded
        >Ingreso de emergencia: la recepción puede realizarse sin esperar el
        permiso docente.</q-banner
      >
      <q-timeline color="primary" layout="dense"
        ><q-timeline-entry
          v-for="event in tracking.events"
          :key="event.id"
          :title="careEventLabels[event.action] || event.action"
          :subtitle="formatDate(event.created_at)"
          icon="task_alt"
          ><span class="muted"
            >{{ event.actor || "Personal del tópico" }} ·
            {{ roleLabels[event.role] }}</span
          ></q-timeline-entry
        ></q-timeline
      >
      <p v-if="!tracking.events.length" class="muted"
        >Este caso se registró antes de habilitar el seguimiento detallado.
        Registrado: {{ formatDate(tracking.request.created_at)
        }}<template v-if="tracking.request.closed_at">
          · Cerrado: {{ formatDate(tracking.request.closed_at) }}</template
        >.</p
      >
    </template>
  </section>
</template>
<script setup lang="ts">
import { computed, ref, watch } from "vue";
import {
  readLive,
  careEventLabels,
  roleLabels,
  type CareTracking
} from "@/services/topico";
const props = defineProps<{ requestId: number; refreshKey?: number }>();
const tracking = ref<CareTracking | null>(null),
  loading = ref(false),
  error = ref("");
const formatDate = (v: string) =>
  new Intl.DateTimeFormat("es-ES", {
    dateStyle: "short",
    timeStyle: "short"
  }).format(new Date(v));
const steps = computed(() => {
  const r = tracking.value?.request;
  const events = tracking.value?.events || [];
  const closed = !!r && ["finalizada", "derivada"].includes(r.status);
  const has = (action: string) => events.some(e => e.action === action);
  return [
    {
      title: "Permiso y derivación",
      owner: "Docente",
      done:
        !!r &&
        (!!r.authorized_at ||
          r.emergency ||
          [
            "autorizada",
            "en_espera",
            "en_atencion",
            "finalizada",
            "derivada"
          ].includes(r.status))
    },
    {
      title: "Recepción y atención",
      owner: "Encargado del tópico",
      done:
        !!r &&
        (!!r.received_at ||
          ["en_atencion", "finalizada", "derivada"].includes(r.status))
    },
    {
      title: "Evaluación y diagnóstico",
      owner: "Encargado del tópico",
      done: closed || has("evaluacion_guardada")
    },
    {
      title: "Tratamiento o derivación",
      owner: "Encargado del tópico",
      done: closed
    },
    {
      title: "Registro recibido",
      owner: "Coordinador",
      done: has("registro_recibido")
    }
  ];
});
async function load() {
  loading.value = true;
  error.value = "";
  try {
    tracking.value = await readLive<CareTracking>(
      "/requests/" + props.requestId + "/tracking"
    );
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    loading.value = false;
  }
}
watch(
  () => [props.requestId, props.refreshKey],
  () => void load(),
  { immediate: true }
);
</script>
<style scoped>
.request-journey {
  border-top: 1px solid #e0eee7;
  margin-top: 22px;
  padding-top: 20px;
}
.journey-steps {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}
.journey-step {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #7c9390;
}
.journey-step > span {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 27px;
  height: 27px;
  border-radius: 50%;
  background: #edf4f1;
  font-size: 12px;
}
.journey-done {
  color: #007f80;
}
.journey-done > span {
  background: #d9f4e9;
}
.journey-step strong {
  display: block;
  font-size: 12px;
}
.journey-step small {
  display: block;
  font-size: 10px;
  margin-top: 2px;
}
.request-journey :deep(.q-timeline__title) {
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 6px;
}
.request-journey :deep(.q-timeline__subtitle) {
  font-size: 10px;
}
</style>
