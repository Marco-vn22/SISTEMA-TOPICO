<template>
  <div>
    <q-banner
      v-if="error"
      class="bg-red-1 text-negative q-mb-md"
      rounded
      role="alert"
      >{{ error }}</q-banner
    >
    <q-banner
      v-if="message"
      class="bg-teal-1 text-primary q-mb-md"
      rounded
      role="status"
      >{{ message }}</q-banner
    >
    <template v-if="section === 'inventory'">
      <div class="row q-gutter-sm q-mb-lg"
        ><q-btn
          unelevated
          color="primary"
          icon="add"
          label="Nuevo producto"
          no-caps
          @click="openProduct()" /><q-btn
          outline
          color="primary"
          icon="swap_vert"
          label="Registrar movimiento"
          no-caps
          @click="openMovement()" /><q-btn
          flat
          color="primary"
          icon="download"
          label="Exportar inventario"
          no-caps
          @click="exportInventory"
      /></div>
      <div class="stats-grid"
        ><q-card flat bordered class="metric"
          ><span class="muted">Productos activos</span
          ><strong>{{ products.filter(p => p.active).length }}</strong
          ><small>Medicamentos e insumos</small></q-card
        ><q-card flat bordered class="metric"
          ><span class="muted">Stock bajo</span
          ><strong class="text-orange-8">{{
            products.filter(p => p.active && p.stock <= p.minimum_stock).length
          }}</strong
          ><small>En el mínimo o por debajo</small></q-card
        ><q-card flat bordered class="metric"
          ><span class="muted">Próximos a vencer</span
          ><strong class="text-orange-8">{{
            products.filter(p => p.expiring_stock > 0).length
          }}</strong
          ><small>Productos con lotes en los próximos 30 días</small></q-card
        ><q-card flat bordered class="metric"
          ><span class="muted">Productos vencidos</span
          ><strong class="text-negative">{{
            products.filter(p => p.expired_stock > 0).length
          }}</strong
          ><small>Lotes excluidos del stock disponible</small></q-card
        ></div
      >
      <q-card flat bordered class="panel"
        ><q-tabs v-model="inventoryTab" align="left" active-color="primary"
          ><q-tab name="products" label="Productos y lotes" no-caps /><q-tab
            name="movements"
            label="Últimos 500 movimientos"
            no-caps /></q-tabs
        ><div class="table-toolbar"
          ><q-input
            outlined
            dense
            v-model="search"
            placeholder="Buscar producto o lote"
            class="search-box"
            ><template #prepend
              ><q-icon name="search" /></template></q-input></div
        ><q-table
          flat
          :rows="
            inventoryTab === 'products' ? filteredProducts : filteredMovements
          "
          :columns="
            inventoryTab === 'products' ? productColumns : movementColumns
          "
          :loading="loading"
          row-key="id"
          no-data-label="Aún no hay registros de inventario"
          rows-per-page-label="Filas por página"
          ><template #body-cell-stock="p"
            ><q-td :props="p"
              ><q-badge
                :class="
                  p.row.stock <= p.row.minimum_stock
                    ? 'badge-orange'
                    : 'badge-teal'
                "
                >{{ p.row.stock }} {{ p.row.unit }}</q-badge
              ><div v-if="p.row.expired_stock" class="text-negative q-mt-xs"
                >{{ p.row.expired_stock }} vencidos</div
              ><div v-if="p.row.expiring_stock" class="text-orange-8 q-mt-xs"
                >{{ p.row.expiring_stock }} próximos a vencer</div
              ></q-td
            ></template
          ><template #body-cell-actions="p"
            ><q-td :props="p"
              ><q-btn
                flat
                color="primary"
                icon="edit"
                aria-label="Editar producto"
                @click="openProduct(p.row)" /><q-btn
                flat
                color="primary"
                label="Lotes"
                no-caps
                @click="
                  selectedProduct = p.row;
                  lotsDialog = true;
                " /><q-btn
                flat
                color="primary"
                icon="swap_vert"
                aria-label="Registrar movimiento"
                @click="openMovement(p.row)" /></q-td></template></q-table
      ></q-card>
    </template>
    <template v-else-if="section === 'prescriptions'">
      <div class="row q-gutter-sm q-mb-lg"
        ><q-btn
          unelevated
          color="primary"
          icon="add"
          label="Registrar receta"
          no-caps
          @click="openPrescription" /></div
      ><q-banner class="bg-teal-1 text-primary q-mb-lg" rounded
        >Registra la indicación del profesional. El stock se descuenta al
        confirmar una entrega, utilizando primero los lotes vigentes que vencen
        antes.</q-banner
      >
      <q-card flat bordered class="panel"
        ><div class="table-toolbar"
          ><q-input
            outlined
            dense
            v-model="search"
            placeholder="Buscar paciente o medicamento"
            class="search-box"
            ><template #prepend
              ><q-icon name="search" /></template></q-input></div
        ><q-table
          flat
          :rows="
            prescriptions.filter(p => matches(p.patient + ' ' + p.product))
          "
          :columns="prescriptionColumns"
          row-key="id"
          :loading="loading"
          no-data-label="Aún no hay recetas registradas"
          rows-per-page-label="Filas por página"
          ><template #body-cell-progress="p"
            ><q-td :props="p"
              ><q-badge
                :class="
                  p.row.dispensed === p.row.quantity
                    ? 'badge-teal'
                    : 'badge-orange'
                "
                >{{ p.row.dispensed }} / {{ p.row.quantity }}
                {{ p.row.unit }}</q-badge
              ></q-td
            ></template
          ><template #body-cell-actions="p"
            ><q-td :props="p"
              ><q-btn
                flat
                color="primary"
                label="Ver receta"
                no-caps
                @click="
                  selectedPrescription = p.row;
                  prescriptionDialog = true;
                " /><q-btn
                v-if="p.row.dispensed < p.row.quantity"
                unelevated
                color="primary"
                label="Entregar"
                no-caps
                @click="openDispense(p.row)" /></q-td></template></q-table
      ></q-card>
    </template>
    <template v-else-if="section === 'referrals'">
      <q-card flat bordered class="panel"
        ><div class="table-toolbar"
          ><q-input
            outlined
            dense
            v-model="search"
            placeholder="Buscar paciente o establecimiento"
            class="search-box"
            ><template #prepend
              ><q-icon name="search" /></template></q-input></div
        ><q-table
          flat
          :rows="
            encounters.filter(
              v =>
                v.outcome === 'derivada' &&
                matches(v.patient?.name + ' ' + v.referral_destination)
            )
          "
          :columns="referralColumns"
          row-key="id"
          :loading="loading"
          no-data-label="No hay derivaciones externas registradas"
          rows-per-page-label="Filas por página"
          ><template #body-cell-followup_status="p"
            ><q-td :props="p"
              ><q-badge
                :class="
                  p.row.followup_status === 'cerrado'
                    ? 'badge-teal'
                    : 'badge-orange'
                "
                >{{ followupLabels[p.row.followup_status] }}</q-badge
              ></q-td
            ></template
          ><template #body-cell-actions="p"
            ><q-td :props="p"
              ><q-btn
                flat
                color="primary"
                label="Seguimiento"
                no-caps
                @click="openFollowup(p.row)" /></q-td></template></q-table
      ></q-card>
    </template>
    <template v-else-if="section === 'reports'">
      <q-card flat bordered class="panel q-mb-lg"
        ><q-form @submit="load" class="report-filters"
          ><q-input
            outlined
            dense
            v-model="range.from"
            type="date"
            label="Desde"
            stack-label
            :rules="[required]" /><q-input
            outlined
            dense
            v-model="range.to"
            type="date"
            label="Hasta"
            stack-label
            :rules="[required]" /><q-btn
            unelevated
            type="submit"
            color="primary"
            label="Consultar"
            no-caps
            :loading="loading" /><q-btn
            flat
            color="primary"
            icon="download"
            label="Exportar resumen CSV"
            no-caps
            @click="exportReport"
            :disable="!report || loading" /></q-form
      ></q-card>
      <template v-if="report"
        ><div class="muted q-mb-md"
          >Período del informe: {{ report.from }} al {{ report.to }}</div
        ><div class="stats-grid"
          ><q-card flat bordered class="metric"
            ><span class="muted">Atenciones</span
            ><strong>{{ report.attentions }}</strong></q-card
          ><q-card flat bordered class="metric"
            ><span class="muted">Pacientes atendidos</span
            ><strong>{{ report.patients_attended }}</strong></q-card
          ><q-card flat bordered class="metric"
            ><span class="muted">Derivaciones externas</span
            ><strong>{{ report.referrals }}</strong></q-card
          ><q-card flat bordered class="metric"
            ><span class="muted">Solicitudes registradas</span
            ><strong>{{
              Object.values(report.statuses).reduce((a, b) => a + Number(b), 0)
            }}</strong></q-card
          ></div
        ><div class="dashboard-grid"
          ><q-card flat bordered class="panel"
            ><div class="panel-heading"
              ><h3>Consumo de medicamentos e insumos</h3></div
            ><q-table
              flat
              :rows="report.consumption"
              :columns="consumptionColumns"
              row-key="name"
              no-data-label="Sin consumo registrado en este período"
              rows-per-page-label="Filas por página" /></q-card
          ><q-card flat bordered class="panel"
            ><div class="panel-heading"
              ><h3>Estado de las solicitudes del período</h3></div
            ><div
              v-if="!Object.keys(report.statuses).length"
              class="empty-state"
              >Sin solicitudes registradas.</div
            ><div
              v-for="(count, status) in report.statuses"
              :key="status"
              class="activity-row"
              ><span class="activity-name">{{ statusLabels[status] }}</span
              ><q-badge class="badge-teal">{{ count }}</q-badge></div
            ></q-card
          ></div
        ></template
      >
    </template>
    <template v-else-if="section === 'records'">
      <div class="dashboard-grid"
        ><q-card flat bordered class="panel"
          ><div class="panel-heading"
            ><h3>Recibir registros de pacientes</h3
            ><q-icon name="upload_file" size="24px" color="primary" /></div
          ><q-card-section
            ><p class="muted"
              >Importa hasta 200 pacientes desde un CSV. Los documentos que ya
              existen actualizan nombre y teléfono; se conservan los demás
              datos.</p
            ><q-btn
              outline
              color="primary"
              icon="download"
              label="Descargar plantilla CSV"
              no-caps
              @click="
                downloadCsv('plantilla-pacientes.csv', [
                  ['documento', 'nombre', 'telefono']
                ])
              " /><q-file
              outlined
              v-model="importFile"
              accept=".csv,text/csv"
              label="Seleccionar archivo CSV"
              class="q-mt-lg"
              clearable
              @update:model-value="parseImport"
              ><template #prepend
                ><q-icon name="attach_file" /></template></q-file
            ><div v-if="importRows.length" class="q-mt-lg"
              ><strong>{{ importRows.length }} registros preparados</strong
              ><q-markup-table flat class="q-mt-sm"
                ><thead
                  ><tr
                    ><th>Documento</th><th>Nombre</th><th>Teléfono</th></tr
                  ></thead
                ><tbody
                  ><tr v-for="row in importRows.slice(0, 5)" :key="row.document"
                    ><td>{{ row.document }}</td
                    ><td>{{ row.name }}</td
                    ><td>{{ row.phone }}</td></tr
                  ></tbody
                ></q-markup-table
              ><p class="muted">Vista previa de los primeros 5 registros.</p
              ><q-btn
                unelevated
                color="primary"
                label="Importar registros"
                no-caps
                :loading="busy"
                @click="importPatients" /></div></q-card-section></q-card
        ><q-card flat bordered class="panel"
          ><div class="panel-heading"
            ><h3>Enviar y consultar registros</h3
            ><q-icon name="download" size="24px" color="primary" /></div
          ><q-card-section
            ><p class="muted"
              >Descarga los registros para su intercambio. La exportación de
              pacientes contiene identificación y contacto; los reportes ofrecen
              totales y consumo.</p
            ><q-btn
              outline
              color="primary"
              label="Exportar pacientes CSV"
              icon="download"
              no-caps
              @click="exportPatients"
              :loading="busy"
            /><q-separator class="q-my-lg" /><p class="muted"
              >Cada importación conserva el usuario responsable en la
              auditoría.</p
            ></q-card-section
          ></q-card
        ></div
      >
    </template>
    <q-card v-else-if="section === 'audit'" flat bordered class="panel"
      ><div class="panel-heading"
        ><div
          ><h3>Últimos 300 eventos</h3
          ><p class="muted"
            >Accesos, cambios y consultas de historial, sin contraseñas ni
            contenido clínico.</p
          ></div
        ></div
      ><q-table
        flat
        :rows="audit"
        :columns="auditColumns"
        row-key="id"
        :loading="loading"
        no-data-label="No hay eventos registrados"
        rows-per-page-label="Filas por página"
    /></q-card>
    <q-inner-loading :showing="loading" color="primary" />
    <q-dialog v-model="formDialog" persistent
      ><q-card class="form-dialog"
        ><q-form @submit="save"
          ><q-card-section class="dialog-heading"
            ><h3>{{ title }}</h3
            ><q-btn
              flat
              round
              icon="close"
              v-close-popup
              :disable="busy" /></q-card-section
          ><q-card-section class="q-gutter-md"
            ><q-banner v-if="formKind === 'dispense'" class="bg-teal-1"
              >{{ selectedPrescription?.patient }} ·
              {{ selectedPrescription?.product }}<br />Pendiente:
              {{
                selectedPrescription
                  ? selectedPrescription.quantity -
                    selectedPrescription.dispensed
                  : 0
              }}
              {{ selectedPrescription?.unit }}</q-banner
            ><q-banner v-if="formKind === 'followup'" class="bg-teal-1"
              ><strong>{{ selectedReferral?.patient?.name }}</strong
              ><br />{{ selectedReferral?.referral_destination }}<br />{{
                selectedReferral?.referral_reason
              }}</q-banner
            ><template v-for="f in visibleFields" :key="f.key"
              ><q-select
                v-if="f.options"
                outlined
                v-model="form[f.key]"
                :label="f.label"
                :options="f.options()"
                emit-value
                map-options
                :rules="f.required ? [required] : []" /><q-toggle
                v-else-if="f.type === 'toggle'"
                v-model="form[f.key]"
                :label="f.label" /><q-input
                v-else
                outlined
                :model-value="inputValue(f.key)"
                @update:model-value="v => (form[f.key] = v)"
                :type="f.type || 'text'"
                :label="f.label"
                :rows="f.type === 'textarea' ? 3 : undefined"
                :stack-label="f.type === 'date'"
                :rules="f.required ? [required] : []"
                :hint="f.hint" /></template
            ><q-banner
              v-if="formError"
              class="bg-red-1 text-negative"
              role="alert"
              >{{ formError }}</q-banner
            ></q-card-section
          ><q-card-actions align="right"
            ><q-btn
              flat
              label="Cancelar"
              v-close-popup
              no-caps
              :disable="busy" /><q-btn
              unelevated
              color="primary"
              type="submit"
              :label="formKind === 'dispense' ? 'Confirmar entrega' : 'Guardar'"
              no-caps
              :loading="busy" /></q-card-actions></q-form></q-card
    ></q-dialog>
    <q-dialog v-model="lotsDialog"
      ><q-card class="form-dialog"
        ><q-card-section class="dialog-heading"
          ><h3>Lotes · {{ selectedProduct?.name }}</h3
          ><q-btn flat round icon="close" v-close-popup /></q-card-section
        ><q-table
          flat
          :rows="selectedProduct?.lots || []"
          :columns="lotColumns"
          row-key="id"
          no-data-label="Registra una entrada para crear el primer lote"
          rows-per-page-label="Filas por página" /></q-card
    ></q-dialog>
    <q-dialog v-model="prescriptionDialog"
      ><q-card class="form-dialog"
        ><q-card-section class="dialog-heading"
          ><h3>Receta #{{ selectedPrescription?.id }}</h3
          ><q-btn flat round icon="close" v-close-popup /></q-card-section
        ><q-card-section v-if="selectedPrescription" class="detail-content"
          ><h3>{{ selectedPrescription.patient }}</h3
          ><p
            ><strong>Atención:</strong> #{{
              selectedPrescription.encounter_id
            }}</p
          ><p
            ><strong>Medicamento:</strong> {{ selectedPrescription.product }}</p
          ><p
            ><strong>Cantidad indicada:</strong>
            {{ selectedPrescription.quantity }}
            {{ selectedPrescription.unit }}</p
          ><h4>Indicación del profesional</h4
          ><p>{{ selectedPrescription.instructions }}</p
          ><q-badge class="badge-teal"
            >Entregado: {{ selectedPrescription.dispensed }} /
            {{ selectedPrescription.quantity }}</q-badge
          ></q-card-section
        ></q-card
      ></q-dialog
    >
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import type { QInputProps, QTableColumn } from "quasar";
import {
  api,
  readLive,
  currentUser,
  statusLabels,
  type Patient,
  type Visit
} from "@/services/topico";
interface Lot {
  id: number;
  batch: string;
  expires_at: string;
  quantity: number;
}
interface Product {
  id: number;
  name: string;
  code: string;
  kind: string;
  unit: string;
  minimum_stock: number;
  active: boolean;
  stock: number;
  expired_stock: number;
  expiring_stock: number;
  lots: Lot[];
}
interface Movement {
  id: number;
  product: string;
  batch: string;
  kind: string;
  quantity: number;
  reason: string;
  creator: string;
  created_at: string;
}
interface Prescription {
  id: number;
  encounter_id: number;
  patient: string;
  product: string;
  product_id: number;
  unit: string;
  quantity: number;
  dispensed: number;
  instructions: string;
  created_at: string;
}
interface Report {
  from: string;
  to: string;
  statuses: Record<string, number>;
  consumption: { name: string; unit: string; quantity: number }[];
  patients_attended: number;
  attentions: number;
  referrals: number;
}
interface ImportRow {
  document: string;
  name: string;
  phone: string;
}
interface Field {
  key: string;
  label: string;
  type?: QInputProps["type"] | "toggle";
  required?: boolean;
  hint?: string;
  show?: () => boolean;
  options?: () => { value: string | number; label: string }[];
}
const props = defineProps<{ section: string; refreshKey: number }>();
const loading = ref(false),
  busy = ref(false),
  error = ref(""),
  message = ref(""),
  search = ref(""),
  inventoryTab = ref("products");
const products = ref<Product[]>([]),
  movements = ref<Movement[]>([]),
  prescriptions = ref<Prescription[]>([]),
  encounters = ref<Visit[]>([]),
  report = ref<Report | null>(null),
  audit = ref<Record<string, unknown>[]>([]);
const localDate = (date: Date) =>
  `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
const range = reactive({
  from: localDate(new Date(new Date().getFullYear(), new Date().getMonth(), 1)),
  to: localDate(new Date())
});
const date = (v: string) =>
  new Intl.DateTimeFormat("es-ES", {
    day: "2-digit",
    month: "short",
    hour: "2-digit",
    minute: "2-digit"
  }).format(new Date(v));
const required = (v: unknown) => !!v || v === 0 || "Este campo es obligatorio";
const matches = (s: string) =>
  s.toLocaleLowerCase().includes(search.value.toLocaleLowerCase());
const filteredProducts = computed(() =>
  products.value.filter(p => matches(p.name + " " + p.code))
);
const filteredMovements = computed(() =>
  movements.value.filter(m => matches(m.product + " " + m.batch))
);
const col = (
  name: string,
  label: string,
  field: QTableColumn["field"] = name
): QTableColumn => ({
  name,
  label,
  field,
  align: name === "actions" ? "right" : "left"
});
const productColumns = [
  col("name", "Producto"),
  col("code", "Código"),
  {
    ...col("kind", "Tipo"),
    format: (v: string) => (v === "medicamento" ? "Medicamento" : "Insumo")
  },
  col("stock", "Disponible"),
  {
    ...col("active", "Estado"),
    format: (v: boolean) => (v ? "Activo" : "Inactivo")
  },
  col("actions", "Acciones")
];
const movementColumns = [
  { ...col("created_at", "Fecha"), format: date },
  col("product", "Producto"),
  col("batch", "Lote"),
  col("kind", "Movimiento"),
  col("quantity", "Cantidad"),
  col("reason", "Motivo"),
  col("creator", "Responsable")
];
const prescriptionColumns = [
  col("patient", "Paciente"),
  col("product", "Medicamento"),
  col("encounter_id", "Atención"),
  col("progress", "Entregado / indicado"),
  col("actions", "Acciones")
];
const followupLabels: Record<string, string> = {
  pendiente: "Pendiente",
  contactado: "Contactado",
  cerrado: "Cerrado"
};
const referralColumns = [
  col("patient", "Paciente", v => v.patient?.name),
  { ...col("created_at", "Fecha"), format: date },
  col("referral_destination", "Establecimiento"),
  col("referral_contact", "Responsable / contacto"),
  col("followup_status", "Seguimiento"),
  col("actions", "Acciones")
];
const consumptionColumns = [
  col("name", "Producto"),
  col("quantity", "Cantidad utilizada"),
  col("unit", "Unidad")
];
const lotColumns = [
  col("batch", "Lote"),
  col("expires_at", "Vencimiento"),
  col("quantity", "Existencias")
];
const auditColumns = [
  { ...col("created_at", "Fecha"), format: date },
  col("user", "Usuario"),
  col("action", "Acción"),
  col("entity", "Registro"),
  col("entity_id", "Identificador")
];
async function load() {
  loading.value = true;
  error.value = "";
  try {
    const jobs: Promise<unknown>[] = [];
    if (["inventory", "prescriptions"].includes(props.section))
      jobs.push(
        readLive<Product[]>("/products").then(d => (products.value = d))
      );
    if (props.section === "inventory")
      jobs.push(
        readLive<Movement[]>("/movements").then(d => (movements.value = d))
      );
    if (props.section === "prescriptions") {
      jobs.push(
        readLive<Prescription[]>("/prescriptions").then(
          d => (prescriptions.value = d)
        )
      );
      jobs.push(
        readLive<Visit[]>("/encounters").then(d => (encounters.value = d))
      );
    }
    if (props.section === "referrals")
      jobs.push(
        readLive<Visit[]>("/encounters").then(d => (encounters.value = d))
      );
    if (props.section === "reports")
      jobs.push(
        readLive<Report>("/reports?" + new URLSearchParams(range)).then(
          d => (report.value = d)
        )
      );
    if (props.section === "audit")
      jobs.push(
        readLive<typeof audit.value>("/audit").then(d => (audit.value = d))
      );
    await Promise.all(jobs);
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    loading.value = false;
  }
}
watch(
  () => [props.section, props.refreshKey],
  (value, previous) => {
    if (previous && formDialog.value) return;
    if (!previous || value[0] !== previous[0]) {
      search.value = "";
      message.value = "";
    }
    void load();
  },
  { immediate: true }
);
const form = reactive<Record<string, string | number | boolean | null>>({}),
  formKind = ref(""),
  title = ref(""),
  fields = ref<Field[]>([]),
  formDialog = ref(false),
  formError = ref("");
const visibleFields = computed(() =>
  fields.value.filter(f => !f.show || f.show())
);
const inputValue = (key: string): string | number | null => {
  const v = form[key];
  return typeof v === "string" || typeof v === "number" ? v : null;
};
function open(
  kind: string,
  heading: string,
  values: Record<string, string | number | boolean | null>,
  schema: Field[]
) {
  Object.keys(form).forEach(k => delete form[k]);
  Object.assign(form, values);
  formKind.value = kind;
  title.value = heading;
  fields.value = schema;
  formError.value = "";
  formDialog.value = true;
}
function openProduct(p?: Product) {
  open(
    "product",
    p ? "Editar producto" : "Nuevo medicamento o insumo",
    {
      id: p?.id || 0,
      name: p?.name || "",
      code: p?.code || "",
      kind: p?.kind || "medicamento",
      unit: p?.unit || "",
      minimum_stock: p?.minimum_stock || 0,
      active: p?.active ?? true
    },
    [
      { key: "name", label: "Nombre y presentación *", required: true },
      { key: "code", label: "Código único *", required: true },
      {
        key: "kind",
        label: "Tipo",
        options: () => [
          { label: "Medicamento", value: "medicamento" },
          { label: "Insumo", value: "insumo" }
        ]
      },
      {
        key: "unit",
        label: "Unidad de medida *",
        required: true,
        hint: "Ejemplo: tabletas, frascos o unidades."
      },
      {
        key: "minimum_stock",
        label: "Stock mínimo *",
        type: "number",
        required: true
      },
      { key: "active", label: "Producto activo", type: "toggle" }
    ]
  );
}
const selectedProduct = ref<Product | null>(null),
  lotsDialog = ref(false);
function openMovement(p?: Product) {
  open(
    "movement",
    "Movimiento de inventario",
    {
      product_id: p?.id || null,
      kind: "entrada",
      quantity: 1,
      reason: "",
      batch: "",
      expires_at: "",
      stock_lot_id: null
    },
    [
      {
        key: "product_id",
        label: "Producto *",
        required: true,
        options: () => products.value.map(v => ({ value: v.id, label: v.name }))
      },
      {
        key: "kind",
        label: "Movimiento",
        options: () => [
          { label: "Entrada de existencias", value: "entrada" },
          { label: "Salida / consumo de insumos", value: "salida" },
          { label: "Baja por descarte o vencimiento", value: "baja" }
        ]
      },
      {
        key: "batch",
        label: "Código del lote *",
        required: true,
        show: () => form.kind === "entrada"
      },
      {
        key: "expires_at",
        label: "Vencimiento del lote *",
        type: "date",
        required: true,
        show: () => form.kind === "entrada"
      },
      {
        key: "stock_lot_id",
        label: "Lote de origen *",
        required: true,
        show: () => form.kind !== "entrada",
        options: () =>
          products.value
            .find(v => v.id === form.product_id)
            ?.lots.filter(l => l.quantity > 0)
            .map(l => ({
              value: l.id,
              label: `${l.batch} · vence ${l.expires_at} · ${l.quantity} disponibles`
            })) || []
      },
      { key: "quantity", label: "Cantidad *", type: "number", required: true },
      {
        key: "reason",
        label: "Motivo / documento de referencia *",
        required: true
      }
    ]
  );
}
function openPrescription() {
  open(
    "prescription",
    "Registrar receta",
    { encounter_id: null, product_id: null, quantity: 1, instructions: "" },
    [
      {
        key: "encounter_id",
        label: "Atención del paciente *",
        required: true,
        options: () =>
          encounters.value
            .filter(v => v.status === "borrador" && v.care_type === "urgencia")
            .map(v => ({
              value: v.id,
              label: `#${v.id} · ${v.patient?.name} · ${date(v.created_at)}`
            }))
      },
      {
        key: "product_id",
        label: "Medicamento *",
        required: true,
        options: () =>
          products.value
            .filter(p => p.kind === "medicamento" && p.active)
            .map(p => ({
              value: p.id,
              label: `${p.name} · ${p.stock} ${p.unit} disponibles`
            }))
      },
      {
        key: "quantity",
        label: "Cantidad indicada *",
        type: "number",
        required: true
      },
      {
        key: "instructions",
        label: "Dosis, frecuencia, duración e indicaciones del profesional *",
        type: "textarea",
        required: true
      }
    ]
  );
}
const selectedPrescription = ref<Prescription | null>(null),
  prescriptionDialog = ref(false);
function openDispense(p: Prescription) {
  selectedPrescription.value = p;
  open(
    "dispense",
    "Registrar entrega de medicamento",
    { id: p.id, quantity: p.quantity - p.dispensed },
    [
      {
        key: "quantity",
        label: "Cantidad a entregar *",
        type: "number",
        required: true
      }
    ]
  );
}
const selectedReferral = ref<Visit | null>(null);
function openFollowup(v: Visit) {
  selectedReferral.value = v;
  open(
    "followup",
    "Seguimiento de derivación",
    {
      id: v.id,
      followup_status: v.followup_status,
      followup_notes: v.followup_notes || "",
      referral_contact: v.referral_contact || ""
    },
    [
      {
        key: "followup_status",
        label: "Estado del seguimiento",
        options: () =>
          Object.entries(followupLabels).map(([value, label]) => ({
            value,
            label
          }))
      },
      {
        key: "referral_contact",
        label: "Responsable o contacto del establecimiento"
      },
      {
        key: "followup_notes",
        label: "Notas de seguimiento *",
        type: "textarea",
        required: true
      }
    ]
  );
}
async function save() {
  busy.value = true;
  formError.value = "";
  try {
    const data = { ...form };
    if (formKind.value === "product")
      await api(
        "/products" + (data.id ? "/" + data.id : ""),
        data.id ? "PUT" : "POST",
        data
      );
    if (formKind.value === "movement") {
      if (data.kind === "entrada") {
        data.stock_lot_id = null;
      } else {
        data.batch = null;
        data.expires_at = null;
      }
      await api("/movements", "POST", data);
    }
    if (formKind.value === "prescription")
      await api("/prescriptions", "POST", data);
    if (formKind.value === "dispense")
      await api("/prescriptions/" + data.id + "/dispense", "POST", data);
    if (formKind.value === "followup")
      await api("/encounters/" + data.id + "/followup", "PATCH", data);
    formDialog.value = false;
    message.value = "Registro guardado correctamente.";
    await load();
  } catch (e) {
    formError.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
function downloadCsv(filename: string, rows: unknown[][]) {
  const escape = (value: unknown) => {
    let text = String(value ?? "");
    if (/^[=+@\-\t\r]/.test(text.trimStart())) text = "'" + text;
    return '"' + text.replaceAll('"', '""') + '"';
  };
  const content =
    "\uFEFF" + rows.map(row => row.map(escape).join(";")).join("\r\n");
  const url = URL.createObjectURL(
    new Blob([content], { type: "text/csv;charset=utf-8;" })
  );
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  link.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
function exportInventory() {
  downloadCsv("inventario-topico.csv", [
    [
      "Codigo",
      "Producto",
      "Tipo",
      "Unidad",
      "Disponible",
      "Stock minimo",
      "Vencido",
      "Proximo a vencer"
    ],
    ...products.value.map(p => [
      p.code,
      p.name,
      p.kind,
      p.unit,
      p.stock,
      p.minimum_stock,
      p.expired_stock,
      p.expiring_stock
    ])
  ]);
}
function exportReport() {
  if (!report.value) return;
  const r = report.value;
  downloadCsv("reporte-topico-" + r.from + "-" + r.to + ".csv", [
    ["Informe", "Desde", "Hasta", "Valor"],
    ["Atenciones", r.from, r.to, r.attentions],
    ["Pacientes atendidos", r.from, r.to, r.patients_attended],
    ["Derivaciones", r.from, r.to, r.referrals],
    [],
    ["Estado de solicitudes", "Total"],
    ...Object.entries(r.statuses).map(([s, n]) => [statusLabels[s], n]),
    [],
    ["Consumo de producto", "Unidad", "Cantidad"],
    ...r.consumption.map(c => [c.name, c.unit, c.quantity])
  ]);
}
const importFile = ref<File | null>(null),
  importRows = ref<ImportRow[]>([]);
function readCsv(text: string): string[][] {
  text = text.replace(/^\uFEFF/, "");
  const delimiter = text
    .slice(0, text.indexOf("\n") < 0 ? text.length : text.indexOf("\n"))
    .includes(";")
    ? ";"
    : ",";
  const rows: string[][] = [];
  let row: string[] = [],
    cell = "",
    quoted = false;
  for (let i = 0; i < text.length; i++) {
    const ch = text[i];
    if (ch === '"') {
      if (quoted && text[i + 1] === '"') {
        cell += '"';
        i++;
      } else {
        quoted = !quoted;
      }
    } else if (ch === delimiter && !quoted) {
      row.push(cell.trim());
      cell = "";
    } else if ((ch === "\n" || ch === "\r") && !quoted) {
      if (ch === "\r" && text[i + 1] === "\n") i++;
      row.push(cell.trim());
      if (row.some(v => v)) rows.push(row);
      row = [];
      cell = "";
    } else {
      cell += ch;
    }
  }
  if (quoted) throw new Error("El CSV contiene comillas sin cerrar.");
  row.push(cell.trim());
  if (row.some(v => v)) rows.push(row);
  return rows;
}
async function parseImport() {
  importRows.value = [];
  error.value = "";
  message.value = "";
  if (!importFile.value) return;
  try {
    if (importFile.value.size > 1024 * 1024)
      throw new Error("El archivo no debe superar 1 MB.");
    const rows = readCsv(await importFile.value.text());
    const headers = rows.shift()?.map(h => h.toLowerCase()) || [];
    const find = (a: string, b: string) =>
      headers.findIndex(h => h === a || h === b);
    const documentIndex = find("documento", "document"),
      nameIndex = find("nombre", "name"),
      phoneIndex = find("telefono", "phone");
    if (documentIndex < 0 || nameIndex < 0)
      throw new Error(
        "El CSV debe incluir las columnas documento y nombre. Descarga la plantilla."
      );
    if (!rows.length || rows.length > 200)
      throw new Error("El archivo debe contener entre 1 y 200 pacientes.");
    importRows.value = rows.map(r => ({
      document: r[documentIndex] || "",
      name: r[nameIndex] || "",
      phone: r[phoneIndex] || ""
    }));
    if (importRows.value.some(r => !r.document || !r.name))
      throw new Error("Todos los registros deben tener documento y nombre.");
    if (
      new Set(importRows.value.map(r => r.document)).size !==
      importRows.value.length
    )
      throw new Error("Hay documentos repetidos dentro del archivo.");
  } catch (e) {
    importRows.value = [];
    error.value = (e as Error).message;
  }
}
async function importPatients() {
  busy.value = true;
  error.value = "";
  try {
    const result = await api<{ created: number; updated: number }>(
      "/patients/import",
      "POST",
      { rows: importRows.value }
    );
    message.value = `Importación completada: ${result.created} creados y ${result.updated} actualizados.`;
    importRows.value = [];
    importFile.value = null;
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
async function exportPatients() {
  busy.value = true;
  error.value = "";
  try {
    const rows = await api<Patient[]>("/patients");
    downloadCsv("pacientes-topico.csv", [
      ["documento", "nombre", "telefono"],
      ...rows.map(p => [p.document, p.name, p.phone])
    ]);
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
</script>

<style scoped>
.report-filters {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
  padding: 24px;
}
.report-filters .q-field {
  min-width: 170px;
}
</style>
