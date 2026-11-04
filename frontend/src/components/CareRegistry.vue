<template>
  <div>
    <q-banner v-if="error" rounded class="bg-red-1 text-negative q-mb-md">{{
      error
    }}</q-banner>
    <q-banner v-if="message" rounded class="bg-teal-1 text-primary q-mb-md">{{
      message
    }}</q-banner>
    <q-banner class="bg-teal-1 text-primary q-mb-lg" rounded
      >Aquí llegan las atenciones y derivaciones cerradas por el encargado.
      Recibe cada registro para completar el proceso administrativo; el
      diagnóstico y las indicaciones permanecen en el historial
      clínico.</q-banner
    >
    <div class="row q-gutter-sm q-mb-lg"
      ><q-chip color="teal-1" text-color="primary"
        >{{ rows.filter(r => !r.reviewed_at).length }} por recibir</q-chip
      ><q-chip>{{ rows.filter(r => r.reviewed_at).length }} recibidos</q-chip
      ><q-space /><q-btn
        outline
        color="primary"
        icon="download"
        label="Exportar registros CSV"
        no-caps
        @click="exportRows"
        :disable="loading || !filtered.length"
    /></div>
    <q-card flat bordered class="panel"
      ><div class="table-toolbar"
        ><q-input
          outlined
          dense
          v-model="search"
          label="Buscar paciente, DNI o registro"
          class="search-box"
          ><template #prepend><q-icon name="search" /></template></q-input
        ><q-select
          outlined
          dense
          v-model="filter"
          emit-value
          map-options
          label="Recepción"
          :options="[
            { label: 'Todos', value: 'all' },
            { label: 'Pendientes', value: 'pending' },
            { label: 'Recibidos', value: 'received' }
          ]"
          style="min-width: 170px" /><q-btn
          flat
          round
          icon="refresh"
          aria-label="Actualizar registros"
          :loading="loading"
          @click="load" /></div
      ><q-table
        flat
        :rows="filtered"
        :columns="columns"
        row-key="id"
        :loading="loading"
        no-data-label="No hay registros cerrados en esta vista"
        rows-per-page-label="Filas por página"
        ><template #body-cell-patient="p"
          ><q-td :props="p"
            ><strong>{{ p.row.patient.name }}</strong
            ><div class="muted"
              >{{ p.row.patient.document }} ·
              {{ p.row.laboratory || "Ubicación no registrada" }}</div
            ></q-td
          ></template
        ><template #body-cell-review="p"
          ><q-td :props="p"
            ><q-badge
              :class="p.row.reviewed_at ? 'badge-teal' : 'badge-orange'"
              >{{ p.row.reviewed_at ? "Recibido" : "Por recibir" }}</q-badge
            ><div v-if="p.row.reviewed_at" class="muted"
              >{{ p.row.reviewed_by }} ·
              {{ formatDate(p.row.reviewed_at) }}</div
            ></q-td
          ></template
        ><template #body-cell-actions="p"
          ><q-td :props="p"
            ><q-btn
              flat
              color="primary"
              label="Seguimiento"
              no-caps
              @click="
                selected = p.row;
                detailDialog = true;
              " /><q-btn
              v-if="!p.row.reviewed_at"
              unelevated
              color="primary"
              label="Recibir registro"
              no-caps
              :loading="receiving === p.row.id"
              :disable="receiving !== null"
              @click="receive(p.row)" /></q-td></template></q-table
    ></q-card>
    <q-dialog v-model="detailDialog"
      ><q-card class="form-dialog"
        ><q-card-section class="dialog-heading"
          ><h3>Registro #{{ selected?.id }}</h3
          ><q-btn flat round icon="close" v-close-popup /></q-card-section
        ><q-card-section v-if="selected"
          ><h3>{{ selected.patient.name }}</h3
          ><p>{{ statusLabels[selected.outcome] }} · {{ selected.clinician }}</p
          ><RequestJourney
            :key="selected.id + '-' + revision"
            :request-id="selected.request_id" /></q-card-section></q-card
    ></q-dialog>
  </div>
</template>
<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import type { QTableColumn } from "quasar";
import { api, readLive, statusLabels, statusEmoji } from "@/services/topico";
import RequestJourney from "./RequestJourney.vue";
interface RegistryRecord {
  id: number;
  request_id: number;
  patient: {
    id: number;
    name: string;
    document: string;
  };
  clinician: string;
  laboratory: string | null;
  schedule_reference: string | null;
  care_type: string;
  outcome: string;
  closed_at: string;
  prescriptions_count: number;
  reviewed_at: string | null;
  reviewed_by: string | null;
}
const props = defineProps<{ refreshKey: number }>();
const rows = ref<RegistryRecord[]>([]),
  loading = ref(false),
  receiving = ref<number | null>(null),
  search = ref(""),
  filter = ref("all"),
  error = ref(""),
  message = ref(""),
  selected = ref<RegistryRecord | null>(null),
  detailDialog = ref(false),
  revision = ref(0);
const formatDate = (v: string) =>
  new Intl.DateTimeFormat("es-ES", {
    dateStyle: "short",
    timeStyle: "short"
  }).format(new Date(v));
const filtered = computed(() =>
  rows.value.filter(
    r =>
      (filter.value === "all" ||
        (filter.value === "pending" ? !r.reviewed_at : !!r.reviewed_at)) &&
      `${r.patient.name} ${r.patient.document} ${r.id}`
        .toLowerCase()
        .includes(search.value.toLowerCase())
  )
);
const columns: QTableColumn[] = [
  { name: "id", label: "Registro", field: "id", align: "left" },
  {
    name: "patient",
    label: "Paciente",
    field: r => r.patient.name,
    align: "left"
  },
  {
    name: "closed_at",
    label: "Cierre",
    field: "closed_at",
    format: formatDate,
    align: "left"
  },
  {
    name: "outcome",
    label: "Resultado",
    field: "outcome",
    format: v => statusEmoji(String(v)) + " " + (statusLabels[v] || v),
    align: "left"
  },
  { name: "clinician", label: "Encargado", field: "clinician", align: "left" },
  { name: "review", label: "Recepción", field: "reviewed_at", align: "left" },
  { name: "actions", label: "Acciones", field: "id", align: "right" }
];
async function load() {
  loading.value = true;
  error.value = "";
  try {
    rows.value = await readLive<RegistryRecord[]>("/care-register");
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    loading.value = false;
  }
}
async function receive(row: RegistryRecord) {
  receiving.value = row.id;
  error.value = "";
  try {
    const updated = await api<RegistryRecord>(
      "/care-register/" + row.id + "/receive",
      "POST"
    );
    rows.value = rows.value.map(r => (r.id === updated.id ? updated : r));
    revision.value++;
    message.value =
      "Registro recibido. El proceso administrativo queda completado.";
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    receiving.value = null;
  }
}
function exportRows() {
  const escape = (v: unknown) => {
    let s = String(v ?? "");
    if (/^[=+@\-\t\r]/.test(s.trimStart())) s = "'" + s;
    return '"' + s.replaceAll('"', '""') + '"';
  };
  const data = [
    [
      "Registro",
      "Solicitud",
      "Paciente",
      "DNI",
      "Laboratorio",
      "Horario de referencia",
      "Encargado",
      "Resultado",
      "Fecha de cierre",
      "Recetas",
      "Recibido por",
      "Fecha de recepción"
    ],
    ...filtered.value.map(r => [
      r.id,
      r.request_id,
      r.patient.name,
      r.patient.document,
      r.laboratory,
      r.schedule_reference,
      r.clinician,
      statusLabels[r.outcome],
      r.closed_at,
      r.prescriptions_count,
      r.reviewed_by,
      r.reviewed_at
    ])
  ];
  const url = URL.createObjectURL(
    new Blob(["\uFEFF" + data.map(r => r.map(escape).join(";")).join("\r\n")], {
      type: "text/csv;charset=utf-8;"
    })
  );
  const link = document.createElement("a");
  link.href = url;
  link.download = "registro-atenciones-topico.csv";
  link.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
watch(
  () => props.refreshKey,
  () => {
    if (receiving.value === null) void load();
  }
);
onMounted(load);
</script>
