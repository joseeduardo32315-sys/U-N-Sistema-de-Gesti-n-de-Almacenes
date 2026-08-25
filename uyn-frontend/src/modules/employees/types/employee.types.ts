import type { Area } from "@/modules/areas/types/area.types";

export type WorkerType = 'internal' | 'external'
export type EmployeeStatus = 'active' | 'inactive'

export interface Employee {
    id: number
    name: string
    worker_type: WorkerType
    worker_type_label: string
    phone: string
    status: EmployeeStatus
    notes: string | null
    area: Area | null
    created_at: string
    updated_at: string
}

export interface EmployeesQuery {
    search?: string
    area_id?: number | ''
    worker_type?: WorkerType | ''
    status?: EmployeeStatus | ''
    page?: number
    per_page?: number
}

export interface CreateEmployeePayload {
    name: string
    area_id: number
    worker_type: WorkerType
    phone: string
    status: EmployeeStatus
    notes?: string
}

export interface UpdateEmployeePayload {
    name: string
    area_id: number
    worker_type: WorkerType
    phone: string
    notes?: string
}