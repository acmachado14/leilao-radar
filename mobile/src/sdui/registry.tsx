import { useEffect, useMemo, useRef, useState, type Dispatch, type ReactNode, type SetStateAction } from 'react';
import {
  Animated,
  Easing,
  Image,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  useWindowDimensions,
  View,
  type TextInputProps,
} from 'react-native';
import { useAppTheme, type ThemeColors } from '../theme';
import { loadStoreProductMap } from '../purchases';
import type { SduiAction, SduiNode } from './types';

type Props = {
  node: SduiNode;
  form: Record<string, unknown>;
  setForm: Dispatch<SetStateAction<Record<string, unknown>>>;
  onAction: (action: SduiAction) => void;
};

export function RenderNode(props: Props): ReactNode {
  const { node, form, setForm, onAction } = props;
  const { colors, scheme } = useAppTheme();
  const styles = useThemedStyles();
  const p = node.props ?? {};

  switch (node.type) {
    case 'Hero':
      return (
        <View key={node.id} style={styles.hero}>
          {p.kicker ? <Text style={styles.kicker}>{String(p.kicker)}</Text> : null}
          <Text style={styles.heroTitle}>{String(p.title ?? '')}</Text>
          {p.subtitle ? <Text style={styles.muted}>{String(p.subtitle)}</Text> : null}
        </View>
      );
    case 'Banner':
      return (
        <View
          key={node.id}
          style={[
            styles.banner,
            p.tone === 'danger' && styles.bannerDanger,
            p.tone === 'success' && styles.bannerSuccess,
          ]}
        >
          <Text style={styles.text}>{String(p.text ?? '')}</Text>
        </View>
      );
    case 'Text':
      return (
        <Text
          key={node.id}
          style={p.tone === 'title' ? styles.title : p.tone === 'muted' ? styles.muted : styles.text}
        >
          {String(p.value ?? '')}
        </Text>
      );
    case 'Image':
      return p.src ? (
        <Image key={node.id} source={{ uri: String(p.src) }} style={styles.image} />
      ) : null;
    case 'Gallery':
      return <GalleryBlock key={node.id} photos={asStringList(p.photos)} alt={String(p.alt ?? '')} />;
    case 'Group':
      return (
        <View key={node.id} style={styles.group}>
          {p.header ? <Text style={styles.groupHeader}>{String(p.header)}</Text> : null}
          <View style={styles.groupCard}>
            {(node.children ?? []).map((child, index) => (
              <View key={child.id ?? `${child.type}-${index}`}>
                {index > 0 ? <View style={styles.groupHairline} /> : null}
                <RenderNode {...props} node={child} />
              </View>
            ))}
          </View>
          {p.footer ? <Text style={styles.groupFooter}>{String(p.footer)}</Text> : null}
        </View>
      );
    case 'Row': {
      const pressable = Boolean(node.onPress) || Boolean(p.disclosure);
      const body = (
        <View style={styles.settingsRow}>
          <Text style={styles.settingsLabel}>{String(p.label ?? '')}</Text>
          <View style={styles.settingsValueWrap}>
            {p.value ? <Text style={styles.settingsValue}>{String(p.value)}</Text> : null}
            {pressable ? <Text style={styles.chevron}>›</Text> : null}
          </View>
        </View>
      );
      return node.onPress ? (
        <Pressable key={node.id} onPress={() => onAction(node.onPress!)}>
          {body}
        </Pressable>
      ) : (
        <View key={node.id}>{body}</View>
      );
    }
    case 'Button':
      return (
        <Pressable
          key={node.id}
          onPress={() => node.onPress && onAction(node.onPress)}
          style={[
            styles.button,
            p.style === 'secondary' && styles.buttonSecondary,
            p.style === 'plain' && styles.buttonPlain,
            p.style === 'danger' && styles.buttonDanger,
          ]}
        >
          <Text
            style={[
              styles.buttonLabel,
              (p.style === 'secondary' || p.style === 'plain') && { color: colors.accent },
              p.style === 'plain' && { color: colors.dim, fontWeight: '500' },
            ]}
          >
            {String(p.label ?? 'OK')}
          </Text>
        </Pressable>
      );
    case 'TextField':
      return <TextFieldBlock key={node.id ?? String(p.name ?? 'field')} node={node} form={form} setForm={setForm} />;
    case 'SearchBar': {
      const name = String(p.name ?? 'q');
      return (
        <TextInput
          key={node.id ?? name}
          value={String(form[name] ?? p.value ?? '')}
          onChangeText={(value) => setForm((current) => ({ ...current, [name]: value }))}
          onSubmitEditing={(event) => {
            if (!node.onPress) {
              return;
            }
            const value = event.nativeEvent.text || String(form[name] ?? p.value ?? '');
            onAction({
              ...node.onPress,
              params: { ...(node.onPress.params ?? {}), [name]: value },
            });
          }}
          placeholder={String(p.placeholder ?? 'Buscar')}
          placeholderTextColor={colors.dim}
          keyboardAppearance={scheme}
          style={styles.search}
          returnKeyType="search"
          clearButtonMode="while-editing"
          autoCorrect={false}
          autoCapitalize="none"
          autoComplete="off"
          textContentType="none"
        />
      );
    }
    case 'StepperYear': {
      const name = String(p.name ?? 'year');
      const value = form[name] ?? p.value ?? '';
      return (
        <View key={node.id ?? name} style={styles.field}>
          <Text style={styles.label}>{String(p.label ?? name)}</Text>
          <TextInput
            value={value === null || value === undefined ? '' : String(value)}
            onChangeText={(next) => setForm((current) => ({ ...current, [name]: next }))}
            keyboardType="number-pad"
            placeholder="2018"
            placeholderTextColor={colors.dim}
            keyboardAppearance={scheme}
            style={styles.input}
          />
        </View>
      );
    }
    case 'Checkbox': {
      const name = String(p.name ?? node.id ?? 'check');
      return (
        <View key={node.id ?? name} style={styles.row}>
          <Switch
            value={Boolean(form[name] ?? p.value)}
            onValueChange={(next) => setForm((current) => ({ ...current, [name]: next }))}
            trackColor={{ true: colors.accentDark }}
          />
          <Text style={styles.text}>{String(p.label ?? name)}</Text>
        </View>
      );
    }
    case 'Chip':
      return (
        <Pressable
          key={node.id}
          onPress={() => node.onPress && onAction(node.onPress)}
          style={[styles.chip, p.selected ? styles.chipSelected : null]}
        >
          <Text style={[styles.chipLabel, p.selected ? styles.chipLabelSelected : null]}>
            {String(p.label ?? '')}
          </Text>
        </Pressable>
      );
    case 'ChipRow':
      return (
        <ScrollView
          key={node.id}
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.chipRow}
        >
          {(node.children ?? []).map((child, index) => (
            <View key={child.id ?? `${child.type}-${index}`}>
              <RenderNode {...props} node={child} />
            </View>
          ))}
        </ScrollView>
      );
    case 'FilterSheet':
      return <FilterSheetBlock key={node.id} node={node} form={form} setForm={setForm} onAction={onAction} />;
    case 'List':
      return (
        <View key={node.id} style={styles.list}>
          {(node.children ?? []).map((child, index) => (
            <View key={child.id ?? `${child.type}-${index}`}>
              <RenderNode {...props} node={child} />
            </View>
          ))}
        </View>
      );
    case 'LotCard':
      return <LotCardBlock key={node.id} node={node} onAction={onAction} />;
    case 'EmptyState':
      return (
        <View key={node.id} style={styles.empty}>
          <Text style={styles.title}>{String(p.title ?? '')}</Text>
          <Text style={styles.muted}>{String(p.text ?? '')}</Text>
        </View>
      );
    case 'Paywall':
      return <PaywallBlock key={node.id} props={p} onAction={onAction} />;
    case 'AiLoading':
      return <AiLoadingBlock key={node.id} node={node} />;
    case 'ForceUpdate':
      return (
        <View key={node.id} style={styles.empty}>
          <Text style={styles.title}>Atualize o app</Text>
          <Text style={styles.muted}>{String(p.text ?? '')}</Text>
        </View>
      );
    case 'Stack':
    case 'Screen':
      return (
        <View key={node.id}>
          {(node.children ?? []).map((child, index) => (
            <View key={child.id ?? `${child.type}-${index}`}>
              <RenderNode {...props} node={child} />
            </View>
          ))}
        </View>
      );
    default:
      return (
        <Text key={node.id} style={styles.muted}>
          Componente {node.type} não existe neste IPA.
        </Text>
      );
  }
}

function LotCardBlock({ node, onAction }: { node: SduiNode; onAction: (action: SduiAction) => void }) {
  const styles = useThemedStyles();
  const p = node.props ?? {};
  const tags = asStringList(p.tags);
  const photoCount = Number(p.photo_count ?? 0);

  return (
    <Pressable onPress={() => node.onPress && onAction(node.onPress)} style={styles.feedCard}>
      <View>
        {p.photo ? <Image source={{ uri: String(p.photo) }} style={styles.feedPhoto} /> : <View style={styles.feedPhoto} />}
        {photoCount > 1 ? (
          <View style={styles.photoBadge}>
            <Text style={styles.photoBadgeLabel}>{photoCount} fotos</Text>
          </View>
        ) : null}
      </View>
      <View style={styles.feedBody}>
        <Text style={styles.feedTitle} numberOfLines={2}>
          {String(p.title ?? '')}
        </Text>
        {p.subtitle ? (
          <Text style={styles.muted} numberOfLines={1}>
            {String(p.subtitle)}
          </Text>
        ) : null}
        <View style={styles.priceRow}>
          <PriceCell label="Lance" value={String(p.lance ?? '—')} />
          <PriceCell label="FIPE" value={String(p.fipe ?? '—')} />
          <PriceCell label="vs FIPE" value={String(p.discount ?? '—')} accent />
        </View>
        {tags.length ? (
          <View style={styles.tagRow}>
            {tags.map((tag) => (
              <View key={tag} style={styles.tag}>
                <Text style={styles.tagLabel}>{tag}</Text>
              </View>
            ))}
          </View>
        ) : null}
        {p.patio ? <Text style={styles.muted}>{String(p.patio)}</Text> : null}
      </View>
    </Pressable>
  );
}

function PriceCell({ label, value, accent }: { label: string; value: string; accent?: boolean }) {
  const styles = useThemedStyles();
  return (
    <View style={styles.priceCell}>
      <Text style={styles.priceLabel}>{label}</Text>
      <Text style={[styles.priceValue, accent && styles.priceAccent]} numberOfLines={1}>
        {value}
      </Text>
    </View>
  );
}

function GalleryBlock({ photos, alt }: { photos: string[]; alt: string }) {
  const { colors } = useAppTheme();
  const styles = useThemedStyles();
  const { width, height: windowHeight } = useWindowDimensions();
  const galleryWidth = Math.max(width - 40, 280);
  const height = Math.round(galleryWidth * 0.68);
  const [index, setIndex] = useState(0);
  const [open, setOpen] = useState(false);

  if (photos.length === 0) {
    return <View style={[styles.galleryFrame, { height }]} />;
  }

  return (
    <View>
      <ScrollView
        horizontal
        pagingEnabled
        nestedScrollEnabled
        showsHorizontalScrollIndicator={false}
        onMomentumScrollEnd={(event) => {
          const next = Math.round(event.nativeEvent.contentOffset.x / galleryWidth);
          setIndex(Math.min(Math.max(next, 0), photos.length - 1));
        }}
        style={{ borderRadius: 16, overflow: 'hidden' }}
      >
        {photos.map((uri, photoIndex) => (
          <Pressable key={`${uri}-${photoIndex}`} onPress={() => setOpen(true)}>
            <Image
              source={{ uri }}
              accessibilityLabel={alt}
              style={{ width: galleryWidth, height, backgroundColor: colors.card }}
              resizeMode="cover"
            />
          </Pressable>
        ))}
      </ScrollView>
      <Text style={styles.galleryCount}>
        {index + 1} / {photos.length}
      </Text>
      <Modal visible={open} animationType="fade" onRequestClose={() => setOpen(false)}>
        <View style={styles.lightbox}>
          <Pressable onPress={() => setOpen(false)} style={styles.lightboxClose} hitSlop={12}>
            <Text style={styles.lightboxCloseLabel}>Fechar</Text>
          </Pressable>
          <ScrollView
            horizontal
            pagingEnabled
            showsHorizontalScrollIndicator={false}
            contentOffset={{ x: index * width, y: 0 }}
          >
            {photos.map((uri, photoIndex) => (
              <Image
                key={`full-${uri}-${photoIndex}`}
                source={{ uri }}
                style={{ width, height: windowHeight - 80 }}
                resizeMode="contain"
              />
            ))}
          </ScrollView>
        </View>
      </Modal>
    </View>
  );
}

function TextFieldBlock({
  node,
  form,
  setForm,
}: {
  node: SduiNode;
  form: Record<string, unknown>;
  setForm: Dispatch<SetStateAction<Record<string, unknown>>>;
}) {
  const { colors, scheme } = useAppTheme();
  const styles = useThemedStyles();
  const p = node.props ?? {};
  const name = String(p.name ?? node.id ?? 'field');
  const autofill = fieldAutofill(name, p);
  const initial = String(form[name] ?? p.value ?? '');

  function write(value: string) {
    setForm((current) => ({ ...current, [name]: value }));
  }

  return (
    <View style={styles.field}>
      <Text style={styles.label}>{String(p.label ?? name)}</Text>
      <TextInput
        defaultValue={initial}
        onChangeText={write}
        onChange={(event) => write(event.nativeEvent.text)}
        secureTextEntry={Boolean(p.secure)}
        keyboardType={
          p.keyboard === 'email' ? 'email-address' : p.keyboard === 'number' ? 'number-pad' : 'default'
        }
        autoCapitalize={autofill.autoCapitalize}
        autoCorrect={false}
        spellCheck={false}
        autoComplete={autofill.autoComplete}
        textContentType={autofill.textContentType}
        passwordRules={autofill.passwordRules}
        importantForAutofill="yes"
        returnKeyType={p.secure ? 'go' : p.keyboard === 'email' ? 'next' : 'done'}
        placeholderTextColor={colors.dim}
        keyboardAppearance={scheme}
        style={styles.input}
      />
    </View>
  );
}

function fieldAutofill(name: string, props: Record<string, unknown>): {
  autoComplete: NonNullable<TextInputProps['autoComplete']>;
  textContentType: NonNullable<TextInputProps['textContentType']>;
  autoCapitalize: NonNullable<TextInputProps['autoCapitalize']>;
  passwordRules?: string;
} {
  const hint = String(props.autoComplete ?? '');
  const secure = Boolean(props.secure);

  if (hint === 'username') {
    return { autoComplete: 'username', textContentType: 'username', autoCapitalize: 'none' };
  }
  if (hint === 'email') {
    return { autoComplete: 'email', textContentType: 'emailAddress', autoCapitalize: 'none' };
  }
  if (hint === 'name' || name === 'name') {
    return { autoComplete: 'name', textContentType: 'name', autoCapitalize: 'words' };
  }
  if (hint === 'new-password' || name === 'password_confirmation') {
    return {
      autoComplete: 'new-password',
      textContentType: 'newPassword',
      autoCapitalize: 'none',
      passwordRules: 'minlength: 8;',
    };
  }
  if (hint === 'password' || name === 'password' || secure) {
    return { autoComplete: 'password', textContentType: 'password', autoCapitalize: 'none' };
  }
  if (name === 'email' || props.keyboard === 'email') {
    return { autoComplete: 'username', textContentType: 'username', autoCapitalize: 'none' };
  }

  return { autoComplete: 'off', textContentType: 'none', autoCapitalize: 'sentences' };
}

function AiLoadingBlock({ node }: { node: SduiNode }) {
  const styles = useThemedStyles();
  const p = node.props ?? {};
  const steps = asStringList(p.steps);
  const spin = useRef(new Animated.Value(0)).current;
  const [active, setActive] = useState(0);

  useEffect(() => {
    const loop = Animated.loop(
      Animated.timing(spin, {
        toValue: 1,
        duration: 1400,
        easing: Easing.linear,
        useNativeDriver: true,
      }),
    );
    loop.start();
    const timer = setInterval(() => {
      setActive((index) => (index + 1 < steps.length ? index + 1 : index));
    }, 1800);
    return () => {
      loop.stop();
      clearInterval(timer);
    };
  }, [spin, steps.length]);

  const rotate = spin.interpolate({ inputRange: [0, 1], outputRange: ['0deg', '360deg'] });

  return (
    <View style={styles.aiWrap}>
      <View style={styles.aiRadar}>
        <Animated.View style={[styles.aiSweep, { transform: [{ rotate }] }]} />
        <View style={styles.aiPing} />
      </View>
      <Text style={styles.title}>{String(p.title ?? 'IA analisando o lote')}</Text>
      {p.subtitle ? <Text style={styles.muted}>{String(p.subtitle)}</Text> : null}
      <View style={styles.aiSteps}>
        {steps.map((step, index) => (
          <View key={step} style={styles.aiStepRow}>
            <View
              style={[
                styles.aiDot,
                index < active && styles.aiDotDone,
                index === active && styles.aiDotActive,
              ]}
            />
            <Text style={[styles.aiStep, index === active && styles.aiStepActive, index < active && styles.aiStepDone]}>
              {step}
            </Text>
          </View>
        ))}
      </View>
    </View>
  );
}

function FilterSheetBlock({ node, onAction }: Props) {
  const styles = useThemedStyles();
  const [open, setOpen] = useState(false);
  const [localForm, setLocalForm] = useState<Record<string, unknown>>({});
  const p = node.props ?? {};
  const baseParams = (p.params ?? {}) as Record<string, string>;

  function openSheet() {
    setLocalForm(sheetDefaults(node.children ?? []));
    setOpen(true);
  }

  function apply() {
    const params: Record<string, string> = { ...baseParams };
    for (const [key, value] of Object.entries(localForm)) {
      if (value === undefined || value === null || value === '') {
        continue;
      }
      params[key] = typeof value === 'boolean' ? (value ? '1' : '0') : String(value);
    }
    setOpen(false);
    onAction({ type: 'reload_screen', screen: 'catalog', params });
  }

  return (
    <>
      <Pressable onPress={openSheet} style={[styles.chip, Number(p.count) > 0 && styles.chipSelected]}>
        <Text style={[styles.chipLabel, Number(p.count) > 0 && styles.chipLabelSelected]}>
          {String(p.label ?? 'Filtros')}
        </Text>
      </Pressable>
      <Modal visible={open} animationType="slide" presentationStyle="pageSheet" onRequestClose={() => setOpen(false)}>
        <View style={styles.sheet}>
          <View style={styles.sheetBar}>
            <Pressable onPress={() => setOpen(false)} hitSlop={8}>
              <Text style={styles.sheetAction}>Cancelar</Text>
            </Pressable>
            <Text style={styles.sheetTitle}>{String(p.title ?? 'Filtros')}</Text>
            <Pressable onPress={apply} hitSlop={8}>
              <Text style={[styles.sheetAction, styles.sheetDone]}>OK</Text>
            </Pressable>
          </View>
          <ScrollView contentContainerStyle={styles.sheetBody}>
            {(node.children ?? []).map((child, index) => (
              <View key={child.id ?? `${child.type}-${index}`}>
                <RenderNode
                  node={child}
                  form={localForm}
                  setForm={setLocalForm}
                  onAction={(action) => {
                    setOpen(false);
                    onAction(action);
                  }}
                />
              </View>
            ))}
          </ScrollView>
        </View>
      </Modal>
    </>
  );
}

function sheetDefaults(nodes: SduiNode[]): Record<string, unknown> {
  const values: Record<string, unknown> = {};
  for (const node of nodes) {
    const props = node.props ?? {};
    if (typeof props.name === 'string' && props.value !== undefined) {
      values[props.name] = props.value;
    }
    if (node.children?.length) {
      Object.assign(values, sheetDefaults(node.children));
    }
  }
  return values;
}

function PaywallBlock({
  props,
  onAction,
}: {
  props: Record<string, unknown>;
  onAction: (action: SduiAction) => void;
}) {
  const styles = useThemedStyles();
  const packages = Array.isArray(props.packages) ? props.packages : [];
  const [storePrices, setStorePrices] = useState<Record<string, { priceString: string; introPriceString?: string }>>(
    {},
  );

  useEffect(() => {
    let cancelled = false;
    loadStoreProductMap()
      .then((map) => {
        if (!cancelled) {
          setStorePrices(map);
        }
      })
      .catch(() => undefined);
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <View>
      {packages.map((item) => {
        const pkg = item as Record<string, unknown>;
        const id = String(pkg.id ?? '');
        const store = storePrices[id];
        const features = Array.isArray(pkg.features) ? pkg.features : [];
        const price = store?.priceString || String(pkg.price_hint ?? '');
        const intro = store?.introPriceString
          ? `7 dias grátis, depois ${store.priceString}/mês`
          : String(pkg.intro ?? pkg.price_hint ?? '');
        return (
          <View key={id} style={[styles.planCard, pkg.highlight ? styles.cardHighlight : null]}>
            <Text style={styles.title}>{String(pkg.title ?? '')}</Text>
            <Text style={styles.paywallPeriod}>{String(pkg.period ?? 'Mensal')} · renovação automática</Text>
            <Text style={styles.paywallPrice}>{price}</Text>
            <Text style={styles.muted}>{intro}</Text>
            {features.map((feature) => (
              <Text key={String(feature)} style={styles.text}>
                • {String(feature)}
              </Text>
            ))}
            {pkg.purchasable === false ? (
              <Text style={styles.muted}>Já incluso no seu plano</Text>
            ) : (
              <Pressable style={styles.button} onPress={() => onAction({ type: 'purchase', params: { id } })}>
                <Text style={styles.buttonLabel}>Assinar {String(pkg.title ?? '')}</Text>
              </Pressable>
            )}
          </View>
        );
      })}
    </View>
  );
}

function asStringList(value: unknown): string[] {
  if (!Array.isArray(value)) {
    return [];
  }
  return value.filter((item): item is string => typeof item === 'string' && item !== '');
}

function useThemedStyles() {
  const { colors } = useAppTheme();
  return useMemo(() => createRegistryStyles(colors), [colors]);
}

function createRegistryStyles(colors: ThemeColors) {
  return StyleSheet.create({
  hero: { gap: 6, marginBottom: 8 },
  kicker: { color: colors.accent, fontSize: 12, fontWeight: '700', letterSpacing: 1.4, textTransform: 'uppercase' },
  heroTitle: { color: colors.text, fontSize: 28, fontWeight: '700' },
  title: { color: colors.text, fontSize: 20, fontWeight: '700' },
  text: { color: colors.text, fontSize: 16, lineHeight: 22 },
  muted: { color: colors.muted, fontSize: 15, lineHeight: 21 },
  label: { color: colors.muted, fontSize: 13, marginBottom: 6 },
  field: { marginBottom: 12 },
  input: {
    backgroundColor: colors.card,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 12,
    color: colors.text,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 16,
  },
  button: {
    backgroundColor: colors.accent,
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 8,
  },
  buttonSecondary: { backgroundColor: 'transparent', borderWidth: 1, borderColor: colors.border },
  buttonPlain: { backgroundColor: 'transparent' },
  buttonDanger: { backgroundColor: colors.danger },
  buttonLabel: { color: colors.onAccent, fontWeight: '700', fontSize: 16 },
  banner: { backgroundColor: colors.card, borderRadius: 12, padding: 12, marginBottom: 12, borderWidth: 1, borderColor: colors.border },
  bannerDanger: { borderColor: colors.danger },
  bannerSuccess: { borderColor: colors.accent },
  image: { width: '100%', height: 220, borderRadius: 16, marginBottom: 12, backgroundColor: colors.card },
  list: { gap: 10 },
  feedCard: {
    backgroundColor: colors.card,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 18,
    overflow: 'hidden',
    marginBottom: 10,
  },
  feedPhoto: { width: '100%', aspectRatio: 16 / 10, backgroundColor: colors.photoBg },
  feedBody: { paddingHorizontal: 14, paddingVertical: 12, gap: 8 },
  feedTitle: { color: colors.text, fontSize: 17, fontWeight: '700' },
  photoBadge: {
    position: 'absolute',
    right: 10,
    bottom: 10,
    backgroundColor: colors.overlay,
    borderRadius: 8,
    paddingHorizontal: 8,
    paddingVertical: 4,
  },
  photoBadgeLabel: { color: colors.onOverlay, fontSize: 12, fontWeight: '600' },
  priceRow: { flexDirection: 'row', gap: 10, marginTop: 4 },
  priceCell: { flex: 1, gap: 2 },
  priceLabel: { color: colors.dim, fontSize: 11, fontWeight: '700', letterSpacing: 0.4, textTransform: 'uppercase' },
  priceValue: { color: colors.text, fontSize: 15, fontWeight: '700' },
  priceAccent: { color: colors.accent },
  tagRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  tag: {
    backgroundColor: colors.bg,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    paddingHorizontal: 8,
    paddingVertical: 4,
  },
  tagLabel: { color: colors.muted, fontSize: 12, fontWeight: '600' },
  planCard: {
    backgroundColor: colors.card,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 16,
    padding: 16,
    gap: 8,
    marginBottom: 12,
  },
  cardHighlight: { borderColor: colors.accent, borderWidth: 2 },
  paywallPeriod: { color: colors.muted, fontSize: 13, fontWeight: '600' },
  paywallPrice: { color: colors.text, fontSize: 28, fontWeight: '700', letterSpacing: -0.4 },
  empty: { paddingVertical: 32, gap: 8 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10, marginVertical: 8 },
  group: { marginBottom: 8 },
  groupHeader: {
    color: colors.dim,
    fontSize: 13,
    fontWeight: '600',
    letterSpacing: 0.4,
    textTransform: 'uppercase',
    marginBottom: 8,
    marginLeft: 4,
  },
  groupCard: {
    backgroundColor: colors.card,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.border,
    overflow: 'hidden',
  },
  groupHairline: { height: StyleSheet.hairlineWidth, backgroundColor: colors.border, marginLeft: 16 },
  groupFooter: { color: colors.muted, fontSize: 13, lineHeight: 18, marginTop: 8, marginHorizontal: 4 },
  settingsRow: {
    minHeight: 48,
    paddingHorizontal: 16,
    paddingVertical: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  settingsLabel: { color: colors.text, fontSize: 16, flexShrink: 0 },
  settingsValueWrap: { flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end', gap: 6 },
  settingsValue: { color: colors.muted, fontSize: 16, textAlign: 'right', flexShrink: 1 },
  chevron: { color: colors.dim, fontSize: 22, lineHeight: 22 },
  galleryFrame: { width: '100%', borderRadius: 16, backgroundColor: colors.card },
  galleryCount: { color: colors.muted, fontSize: 13, textAlign: 'center', marginTop: 8, marginBottom: 4 },
  lightbox: { flex: 1, backgroundColor: '#000', paddingTop: 56 },
  lightboxClose: { position: 'absolute', top: 56, right: 20, zIndex: 2 },
  lightboxCloseLabel: { color: colors.onOverlay, fontSize: 17, fontWeight: '600' },
  search: {
    backgroundColor: colors.card,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 12,
    color: colors.text,
    paddingHorizontal: 14,
    paddingVertical: 11,
    fontSize: 17,
  },
  chipRow: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 2 },
  chip: {
    borderRadius: 18,
    paddingHorizontal: 14,
    paddingVertical: 8,
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.border,
  },
  chipSelected: { backgroundColor: colors.accent, borderColor: colors.accent },
  chipLabel: { color: colors.text, fontSize: 14, fontWeight: '600' },
  chipLabelSelected: { color: colors.onAccent },
  sheet: { flex: 1, backgroundColor: colors.bg },
  sheetBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  sheetTitle: { color: colors.text, fontSize: 17, fontWeight: '700' },
  sheetAction: { color: colors.muted, fontSize: 17, minWidth: 72 },
  sheetDone: { color: colors.accent, fontWeight: '700', textAlign: 'right' },
  sheetBody: { padding: 20, gap: 14, paddingBottom: 40 },
  aiWrap: { alignItems: 'flex-start', gap: 10, paddingVertical: 8 },
  aiRadar: {
    width: 56,
    height: 56,
    borderRadius: 28,
    borderWidth: 1,
    borderColor: colors.accent,
    backgroundColor: colors.accentSoft,
    overflow: 'hidden',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 4,
  },
  aiSweep: {
    position: 'absolute',
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: 'transparent',
    borderWidth: 14,
    borderTopColor: colors.accent,
    borderRightColor: 'transparent',
    borderBottomColor: 'transparent',
    borderLeftColor: 'transparent',
  },
  aiPing: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: colors.accent,
  },
  aiSteps: { width: '100%', gap: 8, marginTop: 6 },
  aiStepRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  aiDot: { width: 8, height: 8, borderRadius: 4, backgroundColor: colors.border },
  aiDotActive: { backgroundColor: colors.accent, shadowColor: colors.accent, shadowOpacity: 0.6, shadowRadius: 6 },
  aiDotDone: { backgroundColor: colors.accentDark },
  aiStep: { color: colors.dim, fontSize: 15 },
  aiStepActive: { color: colors.text, fontWeight: '600' },
  aiStepDone: { color: colors.accent },
  });
}
