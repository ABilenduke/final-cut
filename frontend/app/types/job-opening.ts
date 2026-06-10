// app/types/job-opening.ts

/** One careers-page opening from GET /api/job-openings. */
export interface JobOpening {
  id: string
  title: string
  department: string
  type: string
  description: string
}

export interface JobOpeningsResponse {
  data: JobOpening[]
}
