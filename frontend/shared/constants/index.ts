export enum OrderType {
  DINE_IN = 'dine_in',
  TAKEAWAY = 'takeaway',
}

export enum OrderStatus {
  OPEN = 'open',
  PAID = 'paid',
  VOID = 'void',
  CANCELLED = 'cancelled',
}

export enum OrderBatchStatus {
  PENDING_CONFIRMATION = 'pending_confirmation',
  CONFIRMED = 'confirmed',
  REJECTED = 'rejected',
}

export enum OrderItemStatus {
  ACTIVE = 'active',
  VOIDED = 'voided',
}

export enum PaymentMethod {
  CASH = 'cash',
  QRIS = 'qris',
  CARD = 'card',
  OTHER = 'other',
}

export enum TableStatus {
  AVAILABLE = 'available',
  OCCUPIED = 'occupied',
  RESERVED = 'reserved',
}

export enum SyncStatus {
  PENDING = 'pending',
  SYNCED = 'synced',
  CONFLICT = 'conflict',
}

export enum FraudAlertRule {
  HIGH_VOID_RATE = 'high_void_rate',
  HIGH_DISCOUNT = 'high_discount',
  CASH_MISMATCH = 'cash_mismatch',
  SEQUENCE_GAP = 'sequence_gap',
}

export enum FraudAlertSeverity {
  LOW = 'low',
  MEDIUM = 'medium',
  HIGH = 'high',
}

export enum FraudAlertStatus {
  OPEN = 'open',
  REVIEWED = 'reviewed',
  DISMISSED = 'dismissed',
}

export enum UserRole {
  ADMIN = 'admin',
  MANAGER = 'manager',
  SUPERVISOR = 'supervisor',
  CASHIER = 'cashier',
  KITCHEN = 'kitchen',
}

export const ORDER_TYPE_LABELS: Record<OrderType, string> = {
  [OrderType.DINE_IN]: 'Makan di Tempat',
  [OrderType.TAKEAWAY]: 'Bawa Pulang',
};

export const ORDER_STATUS_LABELS: Record<OrderStatus, string> = {
  [OrderStatus.OPEN]: 'Open',
  [OrderStatus.PAID]: 'Paid',
  [OrderStatus.VOID]: 'Void',
  [OrderStatus.CANCELLED]: 'Cancelled',
};

export const PAYMENT_METHOD_LABELS: Record<PaymentMethod, string> = {
  [PaymentMethod.CASH]: 'Tunai',
  [PaymentMethod.QRIS]: 'QRIS',
  [PaymentMethod.CARD]: 'Kartu',
  [PaymentMethod.OTHER]: 'Lainnya',
};

export const TABLE_STATUS_LABELS: Record<TableStatus, string> = {
  [TableStatus.AVAILABLE]: 'Tersedia',
  [TableStatus.OCCUPIED]: 'Terisi',
  [TableStatus.RESERVED]: 'Dipesan',
};

export const TABLE_STATUS_COLORS: Record<TableStatus, string> = {
  [TableStatus.AVAILABLE]: '#10B981',
  [TableStatus.OCCUPIED]: '#F59E0B',
  [TableStatus.RESERVED]: '#3B82F6',
};

export const SYNC_STATUS_LABELS: Record<SyncStatus, string> = {
  [SyncStatus.PENDING]: 'Menunggu',
  [SyncStatus.SYNCED]: 'Tersinkronisasi',
  [SyncStatus.CONFLICT]: 'Konflik',
};

export const FRAUD_ALERT_SEVERITY_COLORS: Record<FraudAlertSeverity, string> = {
  [FraudAlertSeverity.LOW]: '#10B981',
  [FraudAlertSeverity.MEDIUM]: '#F59E0B',
  [FraudAlertSeverity.HIGH]: '#EF4444',
};