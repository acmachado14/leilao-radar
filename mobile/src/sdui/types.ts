export type SduiAction = {
  type: string;
  screen?: string;
  action?: string;
  params?: Record<string, string>;
  then?: SduiAction;
  url?: string;
  confirm?: string;
  destructive?: boolean;
};

export type SduiNode = {
  type: string;
  id?: string;
  props?: Record<string, unknown>;
  children?: SduiNode[];
  onPress?: SduiAction;
};

export type SduiTab = {
  id: string;
  label: string;
  screen: string;
  icon?: string;
  active?: boolean;
};

export type ScreenDocument = {
  schema_version: number;
  screen: string;
  title: string;
  refresh?: SduiAction | null;
  components: SduiNode[];
  tabs?: SduiTab[];
  meta?: Record<string, unknown>;
  token?: string;
};
