export interface SpecialProcessPiecePayload {
  piece_type_id: number
  process_id: number
  notes: string | null
}

export interface GarmentCutClassificationPayload {
  complement_notes: string | null
  special_process_pieces: SpecialProcessPiecePayload[]
}