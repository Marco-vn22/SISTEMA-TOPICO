<template>
  <q-card class="encounter-workspace">
    <q-card-section class="dialog-heading"
      ><div
        ><div class="eyebrow">PROCESO DE ATENCIÓN DEL TÓPICO</div
        ><h3>{{ tracking?.request.patient.name || "Atención del paciente" }}</h3
        ><p class="muted q-mb-none"
          >Solicitud #{{ requestId }} ·
          {{
            tracking ? statusLabels[tracking.request.status] : "Cargando…"
          }}</p
        ><p v-if="tracking" class="muted q-mb-none"
          >Procedencia: {{ tracking.request.laboratory || "No registrada"
          }}<template v-if="tracking.request.schedule_reference">
            · {{ tracking.request.schedule_reference }}</template
          ></p
        ></div
      ><div class="row items-center q-gutter-sm">
        <q-btn
          v-if="encounter?.status === 'borrador' && tab !== 'assessment'"
          flat
          no-caps
          color="primary"
          icon="edit_note"
          label="Editar evaluación guardada"
          :disable="busy"
          @click="editAssessment"
        />
        <q-btn flat round icon="close" v-close-popup :disable="busy" /> </div
    ></q-card-section>
    <q-banner
      v-if="error"
      class="bg-red-1 text-negative q-ma-md"
      rounded
      role="alert"
      >{{ error }}</q-banner
    >
    <q-banner
      v-if="message"
      class="bg-teal-1 text-primary q-ma-md"
      rounded
      role="status"
      >{{ message }}</q-banner
    >
    <q-card-section v-if="tracking?.request.patient.allergies" class="q-py-none"
      ><q-banner class="bg-orange-1 text-orange-10" rounded
        ><q-icon name="warning_amber" /> Alergias registradas:
        {{ tracking.request.patient.allergies }}</q-banner
      ></q-card-section
    >
    <q-tabs v-model="tab" align="left" active-color="primary" class="q-mt-sm"
      ><q-tab
        name="assessment"
        label="1. Evaluación y diagnóstico"
        no-caps /><q-tab
        name="treatment"
        label="2. Tratamiento o derivación"
        no-caps
        :disable="!encounter || dirty" /><q-tab
        name="close"
        label="3. Registro y cierre"
        no-caps
        :disable="!encounter || dirty"
    /></q-tabs>
    <q-separator />
    <q-tab-panels v-model="tab" animated>
      <q-tab-panel name="assessment">
        <q-banner
          v-if="encounter?.status === 'borrador'"
          rounded
          class="bg-teal-1 text-primary q-mb-md"
          role="status"
        >
          {{
            dirty
              ? "Tienes cambios sin guardar. Guarda la evaluación antes de continuar."
              : "Evaluación guardada. Puedes modificarla mientras la atención permanezca abierta."
          }}
        </q-banner>
        <q-form @submit="saveAssessment('assessment')" class="q-gutter-md">
          <q-input
            outlined
            v-model="form.symptoms"
            type="textarea"
            rows="2"
            label="Evaluación y síntomas *"
            :rules="[required]"
            :disable="busy"
          />
          <q-input
            outlined
            v-model="form.diagnosis"
            type="textarea"
            rows="2"
            label="Diagnóstico *"
            :rules="[required]"
            :disable="busy"
          />
          <q-input
            outlined
            v-model="form.indications"
            type="textarea"
            rows="2"
            label="Indicaciones y recomendaciones *"
            :rules="[required]"
            :disable="busy"
          />
          <div class="clinical-branch"
            ><strong>Decisión del encargado según la evaluación</strong
            ><q-option-group
              v-model="form.care_type"
              :options="[
                { label: 'Atención en tópico (urgencia)', value: 'urgencia' },
                { label: 'Emergencia: derivación externa', value: 'emergencia' }
              ]"
              type="radio"
              color="primary"
              :disable="busy"
          /></div>
          <p class="muted"
            >La evaluación se guarda antes de registrar recetas o cerrar la
            derivación. Puedes salir y continuar después.</p
          >
          <div class="row justify-end q-gutter-sm"
            ><q-btn
              unelevated
              type="submit"
              color="primary"
              :label="
                encounter
                  ? 'Guardar cambios de evaluación'
                  : 'Guardar evaluación'
              "
              no-caps
              :loading="busy" /><q-btn
              outline
              color="primary"
              no-caps
              label="Continuar al tratamiento"
              :disable="!encounter || dirty || busy"
              @click="tab = 'treatment'"
          /></div>
        </q-form>
      </q-tab-panel>
      <q-tab-panel name="treatment">
        <template v-if="form.care_type === 'urgencia'">
          <h3>Receta y entrega de medicamentos</h3
          ><p class="muted"
            >Registra la indicación del profesional. El inventario se descuenta
            solo al confirmar la entrega.</p
          >
          <q-banner v-if="dirty" class="bg-orange-1 q-mb-md" rounded
            >Guarda los cambios del tratamiento antes de registrar una receta o
            continuar al cierre.</q-banner
          >
          <q-form
            ref="prescriptionForm"
            @submit="prescribe"
            class="prescription-form q-gutter-y-md"
          >
            <q-select
              outlined
              v-model="prescription.product_id"
              :options="productOptions"
              emit-value
              map-options
              label="Medicamento *"
              :rules="[required]"
              :disable="busy || dirty"
            />
            <q-input
              outlined
              v-model.number="prescription.quantity"
              type="number"
              label="Cantidad indicada *"
              min="1"
              step="1"
              :rules="[quantityRule]"
              :disable="busy || dirty"
            />
            <q-input
              outlined
              v-model="prescription.instructions"
              type="textarea"
              rows="2"
              label="Dosis, frecuencia, duración e indicaciones *"
              :rules="[required]"
              :disable="busy || dirty"
            />
            <div class="row justify-end"
              ><q-btn
                outline
                color="primary"
                type="submit"
                icon="add"
                label="Guardar receta"
                no-caps
                :loading="busy"
                :disable="dirty || !productOptions.length"
            /></div>
          </q-form>
          <q-banner
            v-if="!productOptions.length"
            class="bg-grey-2 q-mt-md"
            rounded
            >No hay medicamentos activos en el catálogo. Regístralos en
            Medicamentos e insumos si se requieren.</q-banner
          >
          <div
            v-for="rx in prescriptions"
            :key="rx.id"
            class="prescription-entry"
            ><div
              ><strong>{{ rx.product }}</strong
              ><p>{{ rx.instructions }}</p
              ><q-badge
                :class="
                  rx.dispensed === rx.quantity ? 'badge-teal' : 'badge-orange'
                "
                >Entregado: {{ rx.dispensed }} / {{ rx.quantity }}
                {{ rx.unit }}</q-badge
              ></div
            ><div
              v-if="rx.dispensed < rx.quantity"
              class="prescription-delivery"
              ><q-input
                outlined
                dense
                type="number"
                v-model.number="deliveryQuantities[rx.id]"
                label="Cantidad a entregar"
                min="1"
                :max="rx.quantity - rx.dispensed"
                :disable="busy || dirty" /><q-btn
                unelevated
                color="primary"
                label="Entregar"
                no-caps
                @click="dispense(rx)"
                :loading="busy"
                :disable="dirty" /></div
          ></div>
          <q-input
            v-if="!prescriptions.length"
            outlined
            v-model="form.no_medication_reason"
            class="q-mt-lg"
            type="textarea"
            rows="2"
            label="Motivo si la atención no requiere medicación"
            hint="Completa este campo cuando no corresponda registrar una receta."
            :disable="busy"
          />
        </template>
        <template v-else>
          <h3>Derivación externa por emergencia</h3
          ><p class="muted"
            >Indica el establecimiento que recibirá al paciente y deja
            constancia de la derivación.</p
          >
          <div class="q-gutter-y-md"
            ><q-input
              outlined
              v-model="form.referral_destination"
              label="Establecimiento de destino *"
              :disable="busy" /><q-input
              outlined
              v-model="form.referral_reason"
              type="textarea"
              rows="3"
              label="Motivo de derivación *"
              :disable="busy" /><q-input
              outlined
              v-model="form.referral_contact"
              label="Responsable o contacto del establecimiento"
              :disable="busy"
          /></div>
          <q-banner
            v-if="prescriptions.length"
            class="bg-teal-1 q-mt-md"
            rounded
            >Las recetas registradas antes de cambiar la clasificación se
            conservan como parte de esta atención.</q-banner
          >
        </template>
        <div class="row justify-between q-mt-lg"
          ><q-btn
            flat
            color="primary"
            label="Volver a evaluación"
            no-caps
            @click="tab = 'assessment'"
            :disable="busy" /><q-btn
            unelevated
            color="primary"
            label="Guardar y revisar cierre"
            no-caps
            @click="saveAssessment('close')"
            :loading="busy"
        /></div>
      </q-tab-panel>
      <q-tab-panel name="close">
        <h3>Revisar y cerrar el registro</h3
        ><p class="muted"
          >Al confirmar se actualizará el historial del paciente y el caso
          quedará disponible para el coordinador.</p
        >
        <div class="closure-summary"
          ><p><strong>Paciente:</strong> {{ tracking?.request.patient.name }}</p
          ><p><strong>Diagnóstico:</strong> {{ encounter?.diagnosis }}</p
          ><p
            ><strong>Resultado:</strong>
            {{
              form.care_type === "emergencia"
                ? "Derivación externa"
                : "Atención finalizada en tópico"
            }}</p
          ><p v-if="form.care_type === 'emergencia'"
            ><strong>Destino:</strong>
            {{ form.referral_destination || "Pendiente de registrar" }}</p
          ><p v-else
            ><strong>Recetas:</strong> {{ prescriptions.length
            }}<span v-if="!prescriptions.length">
              ·
              {{
                form.no_medication_reason ||
                "Falta registrar receta o motivo de atención sin medicación."
              }}</span
            ></p
          ></div
        >
        <q-banner class="bg-teal-1 text-primary q-my-lg" rounded
          >El coordinador recibirá el resumen administrativo del caso. Los datos
          clínicos permanecen restringidos al encargado.</q-banner
        >
        <div class="row justify-between"
          ><q-btn
            flat
            label="Volver"
            no-caps
            @click="tab = 'treatment'"
            :disable="busy" /><q-btn
            unelevated
            color="primary"
            :label="
              form.care_type === 'emergencia'
                ? 'Confirmar derivación y cerrar'
                : 'Finalizar atención'
            "
            no-caps
            @click="closeEncounter"
            :loading="busy"
            :disable="dirty"
        /></div>
      </q-tab-panel>
    </q-tab-panels>
    <q-inner-loading :showing="loading" color="primary" />
  </q-card>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref } from "vue";
import type { QForm } from "quasar";
import {
  api,
  statusLabels,
  type CareTracking,
  type Visit,
  type WorkflowPrescription
} from "@/services/topico";
const props = defineProps<{ requestId: number }>();
const emit = defineEmits<{ closed: [] }>();
const tracking = ref<CareTracking | null>(null),
  encounter = ref<Visit | null>(null),
  products = ref<
    {
      id: number;
      name: string;
      kind: string;
      active: boolean;
      stock: number;
      unit: string;
    }[]
  >([]);
const loading = ref(false),
  busy = ref(false),
  error = ref(""),
  message = ref(""),
  tab = ref("assessment");
const form = reactive({
  symptoms: "",
  diagnosis: "",
  indications: "",
  care_type: "urgencia",
  referral_destination: "",
  referral_reason: "",
  referral_contact: "",
  no_medication_reason: ""
});
const snapshot = ref("");
const dirty = computed(() => JSON.stringify(form) !== snapshot.value);
const prescription = reactive({
  product_id: null as number | null,
  quantity: 1,
  instructions: ""
});
const prescriptionForm = ref<QForm | null>(null);
const deliveryQuantities = reactive<Record<number, number>>({});
const prescriptions = computed(() => tracking.value?.prescriptions || []);
const productOptions = computed(() =>
  products.value
    .filter(p => p.active && p.kind === "medicamento")
    .map(p => ({
      value: p.id,
      label: `${p.name} · ${p.stock} ${p.unit} disponibles`
    }))
);
const required = (v: unknown) => !!v || "Este campo es obligatorio";
const quantityRule = (v: unknown) =>
  (Number.isInteger(Number(v)) && Number(v) > 0) ||
  "Ingresa una cantidad entera mayor que cero.";
function hydrate(visit: Visit | null) {
  Object.assign(form, {
    symptoms: visit?.symptoms || tracking.value?.request.reason || "",
    diagnosis: visit?.diagnosis || "",
    indications: visit?.indications || "",
    care_type:
      visit?.care_type ||
      (tracking.value?.request.emergency ? "emergencia" : "urgencia"),
    referral_destination: visit?.referral_destination || "",
    referral_reason: visit?.referral_reason || "",
    referral_contact: visit?.referral_contact || "",
    no_medication_reason: visit?.no_medication_reason || ""
  });
  snapshot.value = visit ? JSON.stringify(form) : "";
}
function editAssessment() {
  tab.value = "assessment";
  message.value =
    "Puedes modificar la evaluación y guardar los cambios antes de finalizar la atención.";
}
async function loadTracking(initial = false) {
  const data = await api<CareTracking>(
    "/requests/" + props.requestId + "/tracking"
  );
  tracking.value = data;
  encounter.value = data.request.encounter || null;
  for (const rx of data.prescriptions || [])
    deliveryQuantities[rx.id] = rx.quantity - rx.dispensed;
  if (initial) hydrate(encounter.value);
}
async function saveAssessment(next: string) {
  busy.value = true;
  error.value = "";
  message.value = "";
  try {
    const updating = !!encounter.value;
    const visit = await api<Visit>(
      "/requests/" + props.requestId + "/assessment",
      "PUT",
      { ...form }
    );
    encounter.value = visit;
    hydrate(visit);
    await loadTracking();
    message.value = updating
      ? "Cambios de evaluación guardados. La atención continúa abierta."
      : "Evaluación y datos de atención guardados. Puedes modificarlos antes de finalizar.";
    tab.value = next;
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
async function prescribe() {
  if (!encounter.value || dirty.value) return;
  busy.value = true;
  error.value = "";
  message.value = "";
  try {
    await api("/prescriptions", "POST", {
      ...prescription,
      encounter_id: encounter.value.id
    });
    Object.assign(prescription, {
      product_id: null,
      quantity: 1,
      instructions: ""
    });
    await nextTick();
    prescriptionForm.value?.resetValidation();
    await loadTracking();
    message.value = "Receta registrada. La entrega se confirma por separado.";
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
async function dispense(rx: WorkflowPrescription) {
  const quantity = Number(deliveryQuantities[rx.id]);
  if (
    !Number.isInteger(quantity) ||
    quantity <= 0 ||
    quantity > rx.quantity - rx.dispensed
  ) {
    error.value =
      "La cantidad debe ser un entero entre 1 y el saldo pendiente de la receta.";
    return;
  }
  busy.value = true;
  error.value = "";
  message.value = "";
  try {
    await api("/prescriptions/" + rx.id + "/dispense", "POST", { quantity });
    await loadTracking();
    products.value = await api<typeof products.value>("/products");
    message.value = "Entrega registrada y existencias actualizadas.";
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
async function closeEncounter() {
  if (!encounter.value || dirty.value) return;
  busy.value = true;
  error.value = "";
  try {
    await api("/encounters/" + encounter.value.id + "/close", "POST");
    emit("closed");
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
onMounted(async () => {
  loading.value = true;
  try {
    await Promise.all([
      loadTracking(true),
      api<typeof products.value>("/products").then(
        data => (products.value = data)
      )
    ]);
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    loading.value = false;
  }
});
</script>
<style scoped>
.encounter-workspace {
  width: 920px;
  max-width: 96vw;
  position: relative;
  border-radius: 16px;
}
.clinical-branch {
  padding: 16px;
  border-radius: 10px;
  background: #f0faf6;
}
.clinical-branch > strong {
  font-size: 13px;
}
.prescription-form {
  padding: 18px;
  border: 1px solid #d8eae2;
  border-radius: 10px;
}
.prescription-entry {
  padding: 16px;
  margin-top: 14px;
  border: 1px solid #d8eae2;
  border-radius: 10px;
}
.prescription-entry p {
  white-space: pre-wrap;
  font-size: 12px;
}
.prescription-delivery {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 15px;
}
.prescription-delivery .q-field {
  max-width: 220px;
}
.closure-summary {
  background: #f6fbf8;
  padding: 16px;
  border-radius: 10px;
}
.closure-summary p {
  white-space: pre-wrap;
  margin: 8px 0;
}
@media (max-width: 600px) {
  .prescription-delivery {
    flex-wrap: wrap;
  }
}
</style>
