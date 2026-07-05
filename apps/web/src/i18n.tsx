import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

export type AppLanguage = "ja" | "en";

const STORAGE_KEY = "cataloga.web.lang";

const ja: Record<string, string> = {
  Dashboard: "ダッシュボード",
  Resources: "リソース",
  "Resource Types": "リソースタイプ",
  Import: "インポート",
  Export: "エクスポート",
  Validation: "検証",
  Administration: "管理",
  Search: "検索",
  Group: "グループ",
  Type: "タイプ",
  Actions: "操作",
  "View resources": "リソース一覧",
  "No Resource Types": "リソースタイプがありません",
  "Create Resource Types from Administration / Resource Types.":
    "管理 / リソースタイプ からリソースタイプを作成してください。",
  All: "すべて",
  "Has resources": "リソースあり",
  Empty: "空",
  "Create Resource Type": "リソースタイプを作成",
  Title: "タイトル",
  Fields: "フィールド",
  Show: "表示",
  Edit: "編集",
  Delete: "削除",
  "Delete Resource Type '{id}'?": "リソースタイプ '{id}' を削除しますか？",
  "Delete {count} Resources with this Resource Type.":
    "このリソースタイプと一緒に {count} 件のリソースを削除します。",
  "This Resource Type has no Resources.":
    "このリソースタイプにはリソースがありません。",
  "Type Resource Type ID to confirm": "確認のためリソースタイプ ID を入力",
  "Confirmation must match Resource Type ID.":
    "リソースタイプ ID と完全に一致する必要があります。",
  Discard: "破棄",
  "Administration / Resource Types": "管理 / リソースタイプ",
  "Administration / Import": "管理 / インポート",
  "Administration / Export": "管理 / エクスポート",
  "Administration / Validation": "管理 / 検証",
  Graph: "グラフ",
  "Resource search": "リソース検索",
  "Search resources": "リソースを検索",
  "No results": "結果がありません",
  "View all resources": "すべてのリソースを見る",
  "No rows.": "表示する行がありません。",
  "No resources": "リソースがありません",
  "Create Resource Types and Resources to populate the graph.":
    "グラフを表示するにはリソースタイプとリソースを作成してください。",
  ShowMode: "表示",
  Connected: "接続あり",
  Isolated: "孤立",
  Fit: "全体表示",
  "Zoom in": "拡大",
  "Zoom out": "縮小",
  "Reset zoom": "拡大率をリセット",
  "Selected resource": "選択中のリソース",
  "Select a node to inspect relations.":
    "ノードを選択すると関係を確認できます。",
  Name: "名前",
  ID: "ID",
  Degree: "接続数",
  "Outgoing references": "参照先",
  "Incoming references": "参照元",
  "No outgoing references.": "参照先はありません。",
  "No incoming references.": "参照元はありません。",
  "Open Resource": "リソースを開く",
  Expand: "拡大",
  Close: "閉じる",
  "Ctrl + scroll to zoom": "Ctrl + スクロールでズーム",
  "Request failed": "リクエストに失敗しました",
  "Preview import": "インポートをプレビュー",
  Preview: "プレビュー",
  "Apply import": "インポートを適用",
  "Resource Types to create": "作成するリソースタイプ",
  "Resource Types to update": "更新するリソースタイプ",
  "Resources to create": "作成するリソース",
  "Resources to update": "更新するリソース",
  "Validation errors": "検証エラー",
  "Export YAML": "YAML をエクスポート",
  "Create Resource": "リソースを作成",
  "Sort by": "並び替え項目",
  Order: "並び順",
  asc: "昇順",
  desc: "降順",
  "Delete Resource '{id}'?": "リソース '{id}' を削除しますか？",
  Metadata: "メタデータ",
  Spec: "スペック",
  Tags: "タグ",
  "No spec fields.": "スペック項目がありません。",
  "Referenced resources": "参照しているリソース",
  "No referenced resources.": "参照しているリソースはありません。",
  "Used by": "このリソースを参照している項目",
  "No resources reference this resource.":
    "このリソースを参照しているリソースはありません。",
  "Custom fields": "カスタムフィールド",
  Dependencies: "依存関係",
  "Raw JSON": "生の JSON",
  loading: "読み込み中",
  Save: "保存",
  Advanced: "詳細",
  "custom_fields JSON": "custom_fields JSON",
  "dependencies JSON": "dependencies JSON",
  Selected: "選択中",
  Clear: "クリア",
  "Unknown target: {id}": "不明な参照先: {id}",
  "This Resource references a missing {targetLabel}.":
    "このリソースは存在しない {targetLabel} を参照しています。",
  "No {targetLabel} selected.": "{targetLabel} が選択されていません。",
  Remove: "削除",
  "No selected resources.": "選択中のリソースはありません。",
  "Search {targetLabel} by name or ID": "名前または ID で {targetLabel} を検索",
  "Loading {targetLabel} resources...":
    "{targetLabel} のリソースを読み込み中...",
  "Failed to load {targetLabel} resources.":
    "{targetLabel} のリソース読み込みに失敗しました。",
  "No {targetLabel} resources.": "{targetLabel} のリソースがありません。",
  "No matching {targetLabel} resources.":
    "一致する {targetLabel} のリソースがありません。",
  "Create {targetLabel}": "{targetLabel} を作成",
  Language: "言語",
  Japanese: "日本語",
  English: "英語",
  Other: "その他",
  "Resource, ID, Resource Type": "リソース、ID、リソースタイプ",
  True: "True",
  False: "False",
  Select: "選択",
  "Reference target is not configured for this field.":
    "このフィールドの参照先が設定されていません。",
  Settings: "設定",
  "Worker and storage settings are configured by environment bindings.":
    "Worker とストレージの設定は環境バインディングで構成されます。",
  "Apply Home Lab Basic template to initialize Resource Types.":
    "Home Lab Basic テンプレートを適用してリソースタイプを初期化します。",
  "Some Resource Types already exist. Overwrite with Home Lab Basic template?":
    "既存のリソースタイプがあります。Home Lab Basic テンプレートで上書きしますか？",
  "Apply Home Lab Basic Template": "Home Lab Basic テンプレートを適用",
  General: "基本",
  Description: "説明",
  "Field Types": "フィールドタイプ",
  "Field Types guide": "フィールドタイプガイド",
  "Add field": "フィールドを追加",
  "Field name": "フィールド名",
  Label: "ラベル",
  Required: "必須",
  No: "いいえ",
  Yes: "はい",
  "Enum values": "列挙値",
  "Add value": "値を追加",
  "Target Resource Type": "対象リソースタイプ",
  "Select type": "タイプを選択",
  "Multiple is derived from field type: reference_array => yes.":
    "複数指定はフィールドタイプから自動決定されます: reference_array => yes。",
  "Multiple is derived from field type: reference => no.":
    "複数指定はフィールドタイプから自動決定されます: reference => no。",
  "List columns": "一覧カラム",
  Path: "パス",
  "Add column": "カラムを追加",
  References: "参照",
  "No references configured.": "参照は設定されていません。",
  "Validation rules": "検証ルール",
  "Advanced JSON": "詳細 JSON",
  Status: "ステータス",
  OK: "OK",
  Failed: "失敗",
  Errors: "エラー",
  Warnings: "警告",
  "Resource Type": "リソースタイプ",
  Resource: "リソース",
  Field: "フィールド",
  Message: "メッセージ",
  Purpose: "用途",
  "Resource form": "入力形式",
  "Stored value": "保存値",
  Example: "例",
  Recommendations: "推奨",
  "Short text.": "短いテキスト。",
  "Text input.": "テキスト入力。",
  "JSON string": "JSON 文字列",
  "Longer text.": "長いテキスト。",
  "Textarea.": "テキストエリア。",
  "Whole number.": "整数。",
  "Number input.": "数値入力。",
  "JSON number": "JSON 数値",
  "Decimal or whole number.": "小数または整数。",
  "True/false value.": "真偽値。",
  "True/False selector.": "True/False 選択。",
  "JSON boolean": "JSON 真偽値",
  "One value from a fixed list.": "固定リストから 1 つを選択。",
  "Select.": "選択。",
  "Link to one Resource of another Resource Type.":
    "別のリソースタイプの単一リソースへの参照。",
  "Select target Resource.": "参照先リソースを選択。",
  "Target Resource ID string": "参照先リソース ID 文字列",
  "Link to multiple Resources of another Resource Type.":
    "別のリソースタイプの複数リソースへの参照。",
  "Multi-select.": "複数選択。",
  "Array of target Resource ID strings": "参照先リソース ID 文字列の配列",
  "Generic list.": "汎用リスト。",
  "JSON array input.": "JSON 配列入力。",
  "JSON array": "JSON 配列",
  "Structured object.": "構造化オブジェクト。",
  "JSON object input.": "JSON オブジェクト入力。",
  "JSON object": "JSON オブジェクト",
  "IP address.": "IP アドレス。",
  "Network prefix.": "ネットワークプレフィックス。",
  "URL.": "URL。",
  "URL input.": "URL 入力。",
  "Use reference instead of plain string when the value points to another Resource.":
    "値が別リソースを指す場合は、単なる文字列ではなく reference を使ってください。",
  "Use enum when allowed values are small and stable.":
    "許可値が少なく安定している場合は enum を使ってください。",
  "Use json only when no simpler field type fits.":
    "より単純な型で表現できない場合のみ json を使ってください。",
  "Use array for simple lists.": "単純なリストには array を使ってください。",
  "Use text for descriptions.": "説明文には text を使ってください。",
  "Use ip and cidr instead of string for network values.":
    "ネットワーク値には string ではなく ip / cidr を使ってください。",
  primary_ip: "primary_ip",
  "Primary IP": "Primary IP",
};

type I18nContextValue = {
  lang: AppLanguage;
  setLang: (lang: AppLanguage) => void;
  t: (key: string) => string;
  tf: (key: string, vars: Record<string, string>) => string;
};

const I18nContext = createContext<I18nContextValue | null>(null);

export function I18nProvider({ children }: { children: ReactNode }) {
  const [lang, setLang] = useState<AppLanguage>("ja");

  useEffect(() => {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === "ja" || stored === "en") {
      setLang(stored);
    } else {
      const browserLang = navigator.language.toLowerCase();
      if (browserLang.startsWith("ja")) setLang("ja");
      else setLang("en");
    }
  }, []);

  useEffect(() => {
    localStorage.setItem(STORAGE_KEY, lang);
    document.documentElement.lang = lang;
  }, [lang]);

  const value = useMemo<I18nContextValue>(
    () => ({
      lang,
      setLang,
      t: (key: string) => {
        if (lang === "en") return key;
        return ja[key] ?? key;
      },
      tf: (key: string, vars: Record<string, string>) => {
        const base = lang === "en" ? key : (ja[key] ?? key);
        return Object.entries(vars).reduce(
          (acc, [name, value]) => acc.replaceAll(`{${name}}`, value),
          base,
        );
      },
    }),
    [lang],
  );

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n() {
  const context = useContext(I18nContext);
  if (!context) {
    throw new Error("useI18n must be used within I18nProvider");
  }
  return context;
}
