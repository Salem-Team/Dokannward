import { getSiteContent, getStoreSettings } from "@/lib/catalog";
import { ContactForm } from "./ContactForm";

export default async function ContactPage() {
  const [store, content] = await Promise.all([
    getStoreSettings(),
    getSiteContent(),
  ]);
  return <ContactForm store={store} copy={content.contact} />;
}
