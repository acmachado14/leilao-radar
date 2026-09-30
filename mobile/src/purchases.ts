type PurchasesStoreProduct = {
  identifier: string;
  priceString?: string;
  introPrice?: { priceString?: string } | null;
};

type PurchasesPackage = {
  identifier: string;
  product: PurchasesStoreProduct;
};

type PurchasesOffering = {
  identifier?: string;
  availablePackages: PurchasesPackage[];
};

type PurchasesModule = {
  configure(options: { apiKey: string }): void;
  logIn(appUserId: string): Promise<unknown>;
  getOfferings(): Promise<{
    current: PurchasesOffering | null;
    all?: Record<string, PurchasesOffering>;
  }>;
  getProducts(productIds: string[]): Promise<PurchasesStoreProduct[]>;
  purchasePackage(pkg: PurchasesPackage): Promise<unknown>;
  purchaseStoreProduct(product: PurchasesStoreProduct): Promise<unknown>;
  restorePurchases(): Promise<unknown>;
};

export type StoreProductInfo = {
  productId: string;
  priceString: string;
  introPriceString?: string;
};

export type PurchaseResult = 'purchased' | 'cancelled' | 'unavailable';

const PRODUCT_IDS = ['radar_monthly', 'radar_pro_monthly'];

let configured = false;

async function load(): Promise<PurchasesModule | null> {
  try {
    const mod = require('react-native-purchases') as { default?: PurchasesModule } & PurchasesModule;
    return (mod.default ?? mod) as PurchasesModule;
  } catch {
    return null;
  }
}

export async function configurePurchases(appUserId?: string): Promise<void> {
  const Purchases = await load();
  const apiKey = process.env.EXPO_PUBLIC_RC_IOS_KEY;
  if (!Purchases || !apiKey) {
    return;
  }
  if (!configured) {
    Purchases.configure({ apiKey });
    configured = true;
  }
  if (appUserId) {
    await Purchases.logIn(appUserId);
  }
}

function packagesFromOfferings(offerings: {
  current: PurchasesOffering | null;
  all?: Record<string, PurchasesOffering>;
}): PurchasesPackage[] {
  const current = offerings.current?.availablePackages ?? [];
  if (current.length > 0) {
    return current;
  }
  const all = Object.values(offerings.all ?? {}).flatMap((offering) => offering.availablePackages ?? []);
  return all;
}

export async function loadStoreProductMap(): Promise<Record<string, StoreProductInfo>> {
  const Purchases = await load();
  if (!Purchases) {
    return {};
  }
  await configurePurchases();
  const map: Record<string, StoreProductInfo> = {};

  try {
    const offerings = await Purchases.getOfferings();
    for (const pkg of packagesFromOfferings(offerings)) {
      const productId = pkg.product?.identifier;
      if (!productId) {
        continue;
      }
      map[productId] = {
        productId,
        priceString: pkg.product.priceString || '',
        introPriceString: pkg.product.introPrice?.priceString || undefined,
      };
    }
  } catch {
    // Fall through to StoreKit product lookup.
  }

  if (Object.keys(map).length === 0) {
    try {
      const products = await Purchases.getProducts(PRODUCT_IDS);
      for (const product of products) {
        map[product.identifier] = {
          productId: product.identifier,
          priceString: product.priceString || '',
          introPriceString: product.introPrice?.priceString || undefined,
        };
      }
    } catch {
      return {};
    }
  }

  return map;
}

export async function purchase(productId: string): Promise<PurchaseResult> {
  const Purchases = await load();
  if (!Purchases) {
    return 'unavailable';
  }
  await configurePurchases();

  try {
    const offerings = await Purchases.getOfferings();
    const pkg = packagesFromOfferings(offerings).find((item) => item.product.identifier === productId);
    if (pkg) {
      await Purchases.purchasePackage(pkg);
      return 'purchased';
    }
  } catch (error) {
    if (isUserCancelled(error)) {
      return 'cancelled';
    }
    if (!isEmptyOfferings(error)) {
      throw error;
    }
  }

  try {
    const products = await Purchases.getProducts([productId]);
    const product = products.find((item) => item.identifier === productId);
    if (!product) {
      return 'unavailable';
    }
    await Purchases.purchaseStoreProduct(product);
    return 'purchased';
  } catch (error) {
    if (isUserCancelled(error)) {
      return 'cancelled';
    }
    throw error;
  }
}

export async function restore(): Promise<boolean> {
  const Purchases = await load();
  if (!Purchases) {
    return false;
  }
  await configurePurchases();
  await Purchases.restorePurchases();
  return true;
}

function isUserCancelled(error: unknown): boolean {
  if (!error || typeof error !== 'object') {
    return false;
  }
  const record = error as { userCancelled?: boolean; code?: number };
  return record.userCancelled === true || record.code === 1;
}

function isEmptyOfferings(error: unknown): boolean {
  if (!error || typeof error !== 'object') {
    return false;
  }
  const record = error as { message?: string; code?: number | string };
  const message = String(record.message ?? '').toLowerCase();
  return message.includes('offerings') || message.includes('no products registered') || record.code === 23;
}
