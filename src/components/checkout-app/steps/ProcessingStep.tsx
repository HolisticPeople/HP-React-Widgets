import { Card } from '@/components/ui/card';

const LoaderIcon = () => (
  <svg className="w-12 h-12 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <circle cx="12" cy="12" r="10" strokeOpacity="0.25" />
    <path d="M12 2a10 10 0 0 1 10 10" strokeLinecap="round" />
  </svg>
);

interface ProcessingStepProps {
  message?: string;
}

export const ProcessingStep = ({ 
  message = 'Processing your order...' 
}: ProcessingStepProps) => {
  return (
    <div className="max-w-md mx-auto py-24">
      <Card className="p-12 bg-card/50 backdrop-blur-sm border-border/50 text-center">
        <div className="text-accent mb-6 flex justify-center">
          <LoaderIcon />
        </div>
        <h2 className="text-2xl font-bold text-foreground mb-2">
          {message}
        </h2>
        <p className="text-muted-foreground">
          Please wait while we confirm your payment.
        </p>
      </Card>
    </div>
  );
};

export default ProcessingStep;


