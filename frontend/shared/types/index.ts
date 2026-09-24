// Core entity types for iRestora POS

export interface User {
  id: string;
  outlet_id: string | null;
  role_id: string;
  email: string;
  full_name: string;
  phone: string;
  is_active: boolean;
  last_login_at: string | null;
  created_at: string;
  updated_at: string;
  role?: Role;
  outlet?: Outlet;
}

export interface Role {
  id: string;
  name: string;
  outlet_id: string | null;
  permissions?: Permission[];
}

export interface Permission {
  id: string;
  code: string;
}

export interface Outlet {
  id: string;
  name: string;
  code: string;
  address: string;
  pb1_rate: number;
  service_charge_rate: number;
  rounding_enabled: boolean;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface Category {
  id: string;
  name: string;
  sort_order: number;
}

export interface Menu {
  id: string;
  category_id: string;
  name: string;
  description: string | null;
  image_url: string | null;
  is_active: boolean;
  price?: number;
  category?: Category;
}

export interface MenuPrice {
  id: string;
  menu_id: string;
  outlet_id: string;
  price: number;
  is_active: boolean;
}

export interface DiningTable {
  id: string;
  outlet_id: string;
  name: string;
  qr_code_token: string;
  status: TableStatus;
  current_order_id: string | null;
  outlet?: Outlet;
  currentOrder?: Order;
}

export type TableStatus = 'available' | 'occupied' | 'reserved';

export interface Order {
  id: string;
  outlet_id: string;
  order_type: OrderType;
  table_id: string | null;
  shift_id: string;
  cashier_id: string;
  order_number: string;
  status: OrderStatus;
  subtotal: number;
  discount_total: number;
  service_charge_total: number;
  pb1_total: number;
  rounding_adjustment: number;
  grand_total: number;
  source: OrderSource;
  device_id: string;
  created_at_client: string;
  synced_at: string | null;
  sync_status: SyncStatus;
  created_at: string;
  updated_at: string;
  table?: DiningTable;
  cashier?: User;
  shift?: Shift;
  payments?: Payment[];
  batches?: OrderBatch[];
  items?: OrderItem[];
}

export type OrderType = 'dine_in' | 'takeaway';
export type OrderStatus = 'open' | 'paid' | 'void' | 'cancelled';
export type OrderSource = 'cashier' | 'customer_self_order';
export type SyncStatus = 'pending' | 'synced' | 'conflict';

export interface OrderBatch {
  id: string;
  order_id: string;
  batch_number: number;
  source: OrderBatchSource;
  status: OrderBatchStatus;
  submitted_at: string;
  confirmed_by: string | null;
  confirmed_at: string | null;
  created_at: string;
  updated_at: string;
  items?: OrderItem[];
}

export type OrderBatchSource = 'cashier' | 'customer_self_order';
export type OrderBatchStatus = 'pending_confirmation' | 'confirmed' | 'rejected';

export interface OrderItem {
  id: string;
  order_id: string;
  order_batch_id: string;
  menu_id: string;
  menu_name_snapshot: string;
  price_snapshot: number;
  qty: number;
  notes: string | null;
  status: OrderItemStatus;
  voided_by: string | null;
  void_reason: string | null;
  created_at: string;
  updated_at: string;
  menu?: Menu;
  batch?: OrderBatch;
}

export type OrderItemStatus = 'active' | 'voided';

export interface Payment {
  id: string;
  order_id: string;
  method: PaymentMethod;
  amount: number;
  reference_number: string | null;
  created_at: string;
}

export type PaymentMethod = 'cash' | 'qris' | 'card' | 'other';

export interface Shift {
  id: string;
  outlet_id: string;
  opened_by: string;
  closed_by: string | null;
  opening_cash: number;
  closing_cash_expected: number | null;
  closing_cash_actual: number | null;
  cash_difference: number | null;
  opened_at: string;
  closed_at: string | null;
  outlet?: Outlet;
  openedBy?: User;
  closedBy?: User;
}

export interface AuditLog {
  id: string;
  outlet_id: string;
  user_id: string;
  action: string;
  target_type: string;
  target_id: string;
  before_value: Record<string, any> | null;
  after_value: Record<string, any> | null;
  reason: string | null;
  approved_by: string | null;
  device_id: string | null;
  ip_address: string | null;
  created_at: string;
}

export interface FraudAlert {
  id: string;
  outlet_id: string;
  shift_id: string | null;
  user_id: string | null;
  rule_code: FraudAlertRule;
  severity: FraudAlertSeverity;
  details: Record<string, any> | null;
  status: FraudAlertStatus;
  reviewed_by: string | null;
  reviewed_at: string | null;
  created_at: string;
  updated_at: string;
  outlet?: Outlet;
  shift?: Shift;
  user?: User;
  reviewedBy?: User;
}

export type FraudAlertRule = 'high_void_rate' | 'high_discount' | 'cash_mismatch' | 'sequence_gap';
export type FraudAlertSeverity = 'low' | 'medium' | 'high';
export type FraudAlertStatus = 'open' | 'reviewed' | 'dismissed';

// API Request/Response types
export interface ApiResponse<T = any> {
  success: boolean;
  message: string;
  data: T | null;
  statusCode: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

// Calculator types
export interface OutletTaxConfig {
  pb1Rate: number;
  serviceChargeRate: number;
  roundingEnabled: boolean;
}

export interface OrderItemInput {
  priceSnapshot: number;
  qty: number;
}

export interface CalculatedTotals {
  subtotal: number;
  discountTotal: number;
  afterDiscount: number;
  serviceChargeTotal: number;
  pb1Base: number;
  pb1Total: number;
  grandTotalRaw: number;
  grandTotal: number;
  roundingAdjustment: number;
}