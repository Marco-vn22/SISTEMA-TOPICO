import { ref, watch } from "vue";

export type Role = "coordinador" | "encargado" | "docente" | "estudiante";
export interface Account {
  id: number;
  name: string;
  dni: string | null;
  email: string | null;
  role: Role;
  uses_initial_password: boolean;
  patient_id?: number | null;
}
export interface Patient {
  id: number;
  user_id: number | null;
  name: string;
  document: string;
  birth_date: string | null;
  phone: string | null;
  emergency_contact: string | null;
  emergency_phone: string | null;
  allergies?: string | null;
  background?: string | null;
}
export interface Visit {
  id: number;
  request_id: number;
  laboratory: string | null;
  schedule_reference: string | null;
  patient_id: number;
  clinician_id: number;
  symptoms: string;
  diagnosis: string;
  indications: string;
  outcome: string;
  status: "borrador" | "cerrada";
  care_type: "urgencia" | "emergencia";
  no_medication_reason: string | null;
  closed_at: string | null;
  coordinator_reviewed_at: string | null;
  referral_destination: string | null;
  referral_reason: string | null;
  followup_status: string;
  followup_notes: string | null;
  referral_contact: string | null;
  created_at: string;
  clinician?: Account;
  patient?: Patient;
}
export interface CareRequest {
  id: number;
  patient_id: number;
  teacher_id: number | null;
  laboratory: string | null;
  schedule_reference: string | null;
  reason: string;
  priority: string;
  status: string;
  emergency: boolean;
  authorization_note: string | null;
  created_at: string;
  patient: Patient;
  authorized_at?: string | null;
  received_at?: string | null;
  closed_at?: string | null;
  teacher?: Account;
  creator?: Account;
  encounter?: Visit | null;
}
export interface Dashboard {
  alerts: {
    low_stock: number;
    expiring: number;
    expired: number;
    pending_referrals: number;
    pending_registry: number;
  } | null;
  total_patients: number;
  today: number;
  waiting: number;
  pending: number;
  completed: number;
  referrals: number;
  recent: CareRequest[];
}
export const currentUser = ref<Account | null>(null);
export const sessionNotice = ref("");
let sessionTimer: ReturnType<typeof setTimeout> | undefined;
let sessionDeadline = 0;
watch(
  currentUser,
  user => {
    if (!user) {
      sessionDeadline = 0;
      if (sessionTimer) clearTimeout(sessionTimer);
    }
  },
  { flush: "sync" }
);

function expireSession() {
  if (!sessionDeadline || Date.now() < sessionDeadline) return;
  sessionDeadline = 0;
  if (sessionTimer) clearTimeout(sessionTimer);
  if (!sessionStorage.getItem("topico_token")) return;
  sessionStorage.removeItem("topico_token");
  sessionNotice.value =
    "Tu sesión ha caducado. Ingresa nuevamente para continuar.";
  currentUser.value = null;
}

document.addEventListener("visibilitychange", () => {
  if (!document.hidden) expireSession();
});
export interface WorkflowPrescription {
  id: number;
  product_id: number;
  product: string;
  unit: string;
  quantity: number;
  dispensed: number;
  instructions: string;
}
export interface CareTracking {
  request: CareRequest;
  events: {
    id: number;
    action: string;
    actor: string | null;
    role: Role;
    from_status: string | null;
    to_status: string | null;
    created_at: string;
  }[];
  prescriptions?: WorkflowPrescription[];
}
export const careEventLabels: Record<string, string> = {
  solicitud_registrada: "Solicitud de atención registrada",
  permiso_autorizado: "Permiso autorizado y derivación al tópico",
  ingreso_emergencia: "Ingreso directo por emergencia",
  paciente_recibido: "Paciente recibido en el tópico",
  atencion_iniciada: "Atención iniciada",
  prioridad_actualizada: "Prioridad de atención actualizada",
  evaluacion_guardada: "Evaluación y diagnóstico registrados",
  receta_registrada: "Receta registrada",
  medicamento_entregado: "Entrega de medicamento registrada",
  atencion_finalizada: "Atención cerrada y enviada al registro",
  derivacion_registrada: "Derivación externa registrada",
  registro_recibido: "Registro recibido por el coordinador",
  seguimiento_actualizado: "Seguimiento de derivación actualizado",
  solicitud_cancelada: "Solicitud cancelada o rechazada"
};
export const roleLabels: Record<Role, string> = {
  coordinador: "Coordinador",
  encargado: "Encargado de tópico",
  docente: "Docente",
  estudiante: "Estudiante"
};
export const statusLabels: Record<string, string> = {
  pendiente: "Pendiente de permiso",
  autorizada: "Derivada al tópico",
  en_espera: "En espera",
  en_atencion: "En atención",
  finalizada: "Finalizada",
  derivada: "Derivada",
  cancelada: "Cancelada"
};
export const priorityLabels: Record<string, string> = {
  normal: "Normal",
  urgente: "Urgente",
  emergencia: "Emergencia"
};
export const statusEmoji = (status: string): string => {
  if (status === "finalizada") return "😊";
  if (["pendiente", "autorizada", "en_espera", "derivada"].includes(status))
    return "😔";
  return "";
};
export function readLive<T>(path: string): Promise<T> {
  return api<T>(path, "GET", undefined, { background: true });
}
export async function api<T>(
  path: string,
  method = "GET",
  body?: unknown,
  options: { background?: boolean } = {}
): Promise<T> {
  const token = sessionStorage.getItem("topico_token");
  const response = await fetch("/api/v1" + path, {
    method,
    cache: "no-store",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(options.background ? { "X-Topico-Background": "1" } : {}),
      ...(token ? { Authorization: "Bearer " + token } : {})
    },
    ...(body ? { body: JSON.stringify(body) } : {})
  }).catch(() => {
    throw new Error(
      "No se pudo conectar con el servidor. Revisa la conexión y vuelve a intentarlo."
    );
  });
  if (
    token &&
    path !== "/login" &&
    token !== sessionStorage.getItem("topico_token")
  ) {
    throw new Error("Esta sesión ya no está activa. Ingresa nuevamente.");
  }
  if (token && response.ok) {
    const deadline = Date.parse(
      response.headers.get("X-Session-Expires-At") || ""
    );
    if (Number.isFinite(deadline)) {
      sessionDeadline = deadline;
      if (sessionTimer) clearTimeout(sessionTimer);
      sessionTimer = setTimeout(
        expireSession,
        Math.max(0, deadline - Date.now())
      );
    }
  }
  if (!response.headers.get("content-type")?.includes("application/json")) {
    throw new Error(
      "El servidor no pudo completar la operación. Inténtalo nuevamente."
    );
  }
  const data = await response.json();
  if (!response.ok) {
    if (
      response.status === 403 &&
      data.code === "password_change_required" &&
      currentUser.value
    ) {
      currentUser.value.uses_initial_password = true;
    }
    if (response.status === 401) {
      sessionStorage.removeItem("topico_token");
      sessionDeadline = 0;
      if (sessionTimer) clearTimeout(sessionTimer);
      sessionNotice.value =
        "Tu sesión ha caducado. Ingresa nuevamente para continuar.";
      currentUser.value = null;
    }
    throw new Error(
      data.errors
        ? Object.values(data.errors).flat().join(" ")
        : data.message || "No se pudo completar la operación."
    );
  }
  return data as T;
}
